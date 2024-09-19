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
 * The class contains a test script for the moodle userstatus_timechecker
 *
 * @package    userstatus_timechecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace userstatus_neveloginchecker;

require_once(__DIR__.'/../../../tests/userstatus_base_test.php');


// use userstatus_neverloginchecker\neverloginchecker;

use advanced_testcase;

/**
 * The class contains a test script for the moodle userstatus_timechecker
 *
 * @package    userstatus_timechecker
 * @group      tool_cleanupusers
 * @group      tool_cleanupusers_timechecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \userstatus_neveloginchecker\neverloginchecker::get_to_suspend()
 * @covers \userstatus_neveloginchecker\neverloginchecker::get_to_reactivate()
 *
 */
class userstatus_neverloginchecker_test extends \tool_cleanupusers\userstatus_base_test {

    protected function setup() : void {
        // set enabled plugin for running task
        set_config('userstatus_plugins_enabled', "neverloginchecker");
        set_config('auth_method', AUTH_METHOD, 'userstatus_neverloginchecker');
        set_config('suspendtime', 10, 'userstatus_neverloginchecker');
        set_config('deletetime', 365, 'userstatus_neverloginchecker');
        $this->generator = advanced_testcase::getDataGenerator();
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();
        $this->resetAfterTest(true);
        // TODO??: set_config('deletetime', 365, 'userstatus_nocoursechcker');
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
        $ninedaysago = time() - (86400 * 9);
        $this->create_test_user('username', ['timecreated' => $ninedaysago]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    /**
     * already manually suspended
     * @return void
     * @throws \dml_exception
     */
    public function test_11_days_ago_already_suspended_not_suspend() {
        $elevendaysago = time() - (86400 * 11);
        $user = $this->create_test_user('username', ['timecreated' => $elevendaysago]);
        global $DB;
        $user->suspended = 1;
        $DB->update_record('user', $user);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_11_days_ago_already_deleted_not_suspend() {
        $elevendaysago = time() - (86400 * 11);
        $user = $this->create_test_user('username', ['timecreated' => $elevendaysago]);
        global $DB;
        $user->deleted = 1;
        $DB->update_record('user', $user);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    // ---------------------------------------------
    // Suspend: scenarios handled by this plugin
    // ---------------------------------------------
    public function test_11_days_ago_suspend() {
        $elevendaysago = time() - (86400 * 11);
        $user = $this->create_test_user('username', ['timecreated' => $elevendaysago]);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    public function test_9_days_ago_change_suspend_time_suspend() {
        $ninedaysago = time() - (86400 * 9);
        $user = $this->create_test_user('username', ['timecreated' => $ninedaysago]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));

        // change suspend time to 8 days
        set_config('suspendtime', 8, 'userstatus_neverloginchecker');
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    // ---------------------------------------------
    // Reactivate
    // ---------------------------------------------
    public function test_change_suspend_time_reactivate() {
        $elevendaysago = time() - (86400 * 11);
        $user = $this->create_test_user('username', ['timecreated' => $elevendaysago]);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // change suspend time to 12 days
        set_config('suspendtime', 12, 'userstatus_neverloginchecker');
        // create new checker instance in order to read changes values
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();

        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);
    }

    /**
     * fake an inconsistent database with missing record in archive table
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_change_suspend_time_incomplete_no_reactivate_1() {
        $elevendaysago = time() - (86400 * 11);
        $user = $this->create_test_user('username', ['timecreated' => $elevendaysago]);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        global $DB;
        $DB->delete_records('tool_cleanupusers', ['id' => $user->id]); // NEW for test_invisible_course_make_visisble_reactivate

        // change suspend time to 12 days
        set_config('suspendtime', 12, 'userstatus_neverloginchecker');
        // create new checker instance in order to read changes values
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();

        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

    public function test_change_suspend_time_incomplete_no_reactivate_2() {
        $elevendaysago = time() - (86400 * 11);
        $user = $this->create_test_user('username', ['timecreated' => $elevendaysago]);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        global $DB;
        $DB->delete_records('tool_cleanupusers_archive', ['id' => $user->id]); // NEW for test_invisible_course_make_visisble_reactivate

        // change suspend time to 12 days
        set_config('suspendtime', 12, 'userstatus_neverloginchecker');
        // create new checker instance in order to read changes values
        $this->checker = new \userstatus_neverloginchecker\neverloginchecker();

        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

    /**
     * Function to test the class timechecker.
     *
     * @see timechecker
     */
    /*
    public function test_locallib() {
//        $data = $this->set_up();
        $checker = new neverloginchecker();

        // To suspend.
        $suspend = ["to_suspend"];
        $returnsuspend = $checker->get_to_suspend();
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returnsuspend), $suspend);

        // To reactivate.
        $reactivate = ["to_reactivate"];
        $returnreactivate = $checker->get_to_reactivate();
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returnreactivate), $reactivate);

        // To delete.
        $delete = ["to_delete"];
        $returndelete = $checker->get_to_delete();
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returndelete), $delete);

        // Change configuration
        set_config('suspendtime', 0.5, 'userstatus_neverloginchecker');
        set_config('deletetime', 0.5, 'userstatus_neverloginchecker');
        $newchecker = new neverloginchecker();

        // To suspend.
        $suspend = ["to_suspend", "tu_id_1", "tu_id_2", "tu_id_3", "tu_id_4"];
        $returnsuspend = $newchecker->get_to_suspend();
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returnsuspend), $suspend);

        // To reactivate.
        $reactivate = [];
        $returnreactivate = $newchecker->get_to_reactivate();
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returnreactivate), $reactivate);

        // To delete.
        $delete = ["to_delete", "to_not_delete_one_day", "to_reactivate", "to_not_reactivate_username_taken"];
        $returndelete = $newchecker->get_to_delete();
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returndelete), $delete);

        $this->resetAfterTest(true);
    }
    */
    /**
     * Methodes recommended by moodle to assure database and dataroot is reset.
     */
    public function test_deleting() {
        global $DB;
        $this->resetAfterTest(true);
        $DB->delete_records('user');
        $DB->delete_records('tool_cleanupusers');
        $this->assertEmpty($DB->get_records('user'));
        $this->assertEmpty($DB->get_records('tool_cleanupusers'));
    }
    /**
     * Methodes recommended by moodle to assure database is reset.
     */
    public function test_user_table_was_reset() {
        global $DB;
        $this->assertEquals(2, $DB->count_records('user', []));
        $this->assertEquals(0, $DB->count_records('tool_cleanupusers', []));
    }
}
