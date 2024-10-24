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
 * The class contains a test script for the moodle userstatus_neveloginchecker
 *
 * @package    userstatus_neveloginchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace userstatus_neveloginchecker;

require_once(__DIR__.'/../../../tests/userstatus_base_test.php');


use advanced_testcase;

/**
 * The class contains a test script for the moodle userstatus_neveloginchecker
 *
 * @package    userstatus_neveloginchecker
 * @group      tool_cleanupusers
 * @group      tool_cleanupusers_timechecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 *
 */
#[CoversClass(\userstatus_neverloginchecker\neverloginchecker::class)]
class userstatus_neverloginchecker_test extends \tool_cleanupusers\userstatus_base_test {

    protected function setup() : void {
        // set enabled plugin for running task
        set_config(CONFIG_ENABLED, "neverloginchecker");
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD, 'userstatus_neverloginchecker');
        set_config(CONFIG_SUSPENDTIME, 10, 'userstatus_neverloginchecker');
        set_config(CONFIG_DELETETIME, 365, 'userstatus_neverloginchecker');
        $this->generator = advanced_testcase::getDataGenerator();
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();
        $this->resetAfterTest(true);
    }

    protected function create_checker() {
        return new \userstatus_neverloginchecker\neverloginchecker();
    }

    public function typical_scenario_for_reactivation() : ?\stdClass {
        $user = $this->create_test_user('username', ['timecreated' => ELEVENDAYSAGO]);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // change suspend time to 12 days
        set_config(CONFIG_SUSPENDTIME, 12, 'userstatus_neverloginchecker');
        // create new checker instance in order to read changes values
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();
        return $user;
    }

    public function typical_scenario_for_suspension() : \stdClass {
        return $this->create_test_user('username', ['timecreated' => ELEVENDAYSAGO]);
    }

    // TESTS

    // ---------------------------------------------
    // Suspend: scenarios not handled by this plugin
    // ---------------------------------------------
    public function test_yesterday_no_suspend() {
        $this->create_test_user('username', ['timecreated' => YESTERDAY]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_9_days_ago_no_suspend() {
        $this->create_test_user('username', ['timecreated' => NINEDAYSAGO]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_last_accessed_no_suspend() {
        $user = $this->create_test_user('username',
            ['timecreated' => ELEVENDAYSAGO, 'lastaccess' => YESTERDAY]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    // ---------------------------------------------
    // Suspend: scenarios handled by this plugin
    // ---------------------------------------------
    public function test_change_suspend_time() {
        $user = $this->create_test_user('username', ['timecreated' => NINEDAYSAGO]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));

        // change suspend time to 8 days
        set_config(CONFIG_SUSPENDTIME, 8, 'userstatus_neverloginchecker');
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // change suspend time to 12 days
        set_config(CONFIG_SUSPENDTIME, 12, 'userstatus_neverloginchecker');
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();

        $this->assertEquals(0, count($this->checker->get_to_suspend()));
        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);
    }

}
