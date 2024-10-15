<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The class contains a test script for the moodle userstatus_nocoursechecker
 *
 * @package    userstatus_nocoursechecker
 * @copyright  2016/17 N Herrmann / 2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace userstatus_nocoursechecker;

require_once(__DIR__.'/../../../tests/userstatus_base_test.php');

use advanced_testcase;

/**
 * The class contains a test script for the moodle userstatus_nocoursechecker
 *
 * @package    userstatus_nocoursechecker
 * @group      tool_cleanupusers
 * @group      tool_cleanupusers_timechecker
 * @copyright  2016/17 N Herrmann / 2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \userstatus_nocoursechecker\nocoursechecker::get_to_suspend()
 * @covers \userstatus_nocoursechecker\nocoursechecker::get_to_reactivate()
 * @covers \userstatus_nocoursechecker\nocoursechecker::get_to_delete()
 *
 * get to delete is not handled here as the suplugin is not envolved
 */

final class userstatus_nocoursechecker_test extends \tool_cleanupusers\userstatus_base_test {

    protected function setup() : void {
        $this->generator = advanced_testcase::getDataGenerator();
        $this->resetAfterTest(true);
        // set enabled plugin for running task
        set_config('userstatus_plugins_enabled', "nocoursechecker");
        set_config('auth_method', AUTH_METHOD, 'userstatus_nocoursechecker');
        set_config('deletetime', 365, 'userstatus_nocoursechecker');
        $this->checker = new \userstatus_nocoursechecker\nocoursechecker();
    }

    /**
     * precondition: user is enrolled in a course that is invisible
     *
     * action: course is set to visible
     * expect: reactivate user
     *
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function typical_scenario_for_reactivation() : ?\stdClass {
        $invisible_course = $this->generator->create_course(['startdate' => YESTERDAY, 'visible' => false]);
        $user = $this->create_user_and_enrol('username', $invisible_course);

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        global $DB;
        $invisible_course->visible = true;
        $DB->update_record('course', $invisible_course);
        return $user;
    }

    public function typical_scenario_for_suspension() : \stdClass {
        return $this->create_user_and_enrol('username');
    }

    protected function create_checker() {
        return new \userstatus_nocoursechecker\nocoursechecker();
    }

    // TESTS

    // ---------------------------------------------
    // Suspend: scenarios not handled by this plugin
    // ---------------------------------------------
    public function test_active_course_no_suspend() {
        $active_course = $this->generator->create_course(['startdate' => YESTERDAY, 'enddate' => TOMORROW, 'visible' => true]);
        $this->create_user_and_enrol('username', $active_course);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_other_auth_method_no_suspend() {
        $user = $this->create_user_and_enrol('username');
        global $DB;
        $user->auth = 'email';
        $DB->update_record('user', $user);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_future_course_no_suspend() {
        $future_course = $this->generator->create_course(['startdate' => TOMORROW, 'visible' => true]);
        $this->create_user_and_enrol('username', $future_course);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_open_course_no_suspend() {
        $active_endless_course = $this->generator->create_course(['startdate' => YESTERDAY, 'visible' => true]);
        $this->create_user_and_enrol('username', $active_endless_course);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    /**
     * user is already (manually) suspended. Do not handle with this plugin.
     * @return void
     */
    public function test_user_manually_suspended_no_suspend() {
        $user = $this->create_test_user('manually_suspended', ['suspended' => 1]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    // ---------------------------------------------
    // Suspend: scenarios handled by this plugin
    // ---------------------------------------------
    public function test_no_course_suspend() {
        $user = $this->create_user_and_enrol('username');
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    public function test_invisible_course_suspend() {
        $invisible_course = $this->generator->create_course(['startdate' => YESTERDAY, 'visible' => false]);
        $user = $this->create_user_and_enrol('username', $invisible_course);

        $checker = new \userstatus_nocoursechecker\nocoursechecker();
        $returnsuspend = $checker->get_to_suspend();

        $this->assertEqualsUsersArrays($returnsuspend, $user);
    }

    public function test_past_course_suspend() {
        $past_course = $this->generator->create_course(['startdate' => LAST_MONTH, 'enddate' => YESTERDAY, 'visible' => true]);
        $user = $this->create_user_and_enrol('username', $past_course);

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    /**
     * precondition: user is enrolled in a course that is already finished
     *
     * action: course enddate is set to future date
     * expect: reactivate user
     *
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_past_course_prolong_reactivate() {
        $course = $this->generator->create_course(['startdate' => LAST_MONTH, 'enddate' => YESTERDAY, 'visible' => true]);
        $user = $this->create_user_and_enrol('username', $course);

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        global $DB;
        $course->enddate = TOMORROW;
        $DB->update_record('course', $course);

        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);
    }

    /**
     * pre: enrol user in active course
     * action: manually suspend user
     * expect: not reactivated
     *
     * @return void
     * @throws dml_exception
     */
    public function test_no_reactivate() {
        $active_course = $this->generator->create_course(['startdate' => YESTERDAY, 'enddate' => TOMORROW, 'visible' => true]);
        $user = $this->create_user_and_enrol('username', $active_course);

        global $DB;
        $user->suspended = 1;
        $DB->update_record('user', $user);

        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

}
