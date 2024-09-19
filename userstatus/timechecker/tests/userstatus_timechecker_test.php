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

namespace userstatus_timechecker;

require_once(__DIR__.'/../../../tests/userstatus_base_test.php');

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
 * @covers \userstatus_timechecker\timechecker::get_to_suspend()
 * @covers \userstatus_timechecker\timechecker::get_to_reactivate()
 *
 */
final class userstatus_timechecker_test extends \tool_cleanupusers\userstatus_base_test {

    protected function setup() : void {
        $this->generator = advanced_testcase::getDataGenerator();
        $this->resetAfterTest(true);

        // set enabled plugin for running task
        set_config('userstatus_plugins_enabled', "timechecker");
        set_config('auth_method', AUTH_METHOD, 'userstatus_timechecker');
        set_config('suspendtime', 10, 'userstatus_timechecker');
        set_config('deletetime', 365, 'userstatus_timechecker');

        $this->checker = new \userstatus_timechecker\timechecker();
        // TODO??: set_config('deletetime', 365, 'userstatus_nocoursechcker');
    }

    /**
     * Create the data from the generator.
     * @return mixed
     */
    /*
    protected function set_up() {
        // Recommended in Moodle docs to always include CFG.
        global $CFG;
        $generator = $this->getDataGenerator()->get_plugin_generator('userstatus_timechecker');
        $data = $generator->test_create_preparation();
        $this->resetAfterTest(true);
        set_config('userstatus_plugins_enabled', "neverloginchecker,timechecker");
        // set configuration values for timechecker
        set_config('auth_method', 'shibboleth', 'userstatus_timechecker');
        set_config('suspendtime', 10, 'userstatus_timechecker');
        set_config('deletetime', 365, 'userstatus_timechecker');
        return $data;
    }*/

    public function typical_scenario_for_reactivation() : \stdClass {
        $user = $this->create_test_user('username', ['lastaccess' => ELEVENDAYSAGO]);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // change suspend time to 12 days
        set_config('suspendtime', 12, 'userstatus_timechecker');
        // create new checker instance in order to read changes values
        $this->checker = new \userstatus_timechecker\timechecker();
        return $user;
    }

    public function typical_scenario_for_suspension() : \stdClass {
        return $this->create_test_user('username', ['lastaccess' => ELEVENDAYSAGO]);
    }

    // TESTS
    // ---------------------------------------------
    // Suspend: scenarios not handled by this plugin
    // ---------------------------------------------
    public function test_yesterday_no_suspend() {
        $this->create_test_user('username', ['lastaccess' => YESTERDAY]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_9_days_ago_no_suspend() {
        $this->create_test_user('username', ['lastaccess' => NINEDAYSAGO]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    // ---------------------------------------------
    // Suspend: scenarios handled by this plugin
    // ---------------------------------------------
    public function test_11_days_ago_suspend() {
        $user = $this->create_test_user('username', ['lastaccess' => ELEVENDAYSAGO]);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    public function test_9_days_ago_change_suspend_time_suspend() {
        $user = $this->create_test_user('username', ['lastaccess' => NINEDAYSAGO]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));

        // change suspend time to 8 days
        set_config('suspendtime', 8, 'userstatus_timechecker');
        $this->checker = new \userstatus_timechecker\timechecker();

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }




    /**
     * Function to test the class timechecker.
     *
     * @see timechecker
     */
    /*
    public function test_locallib(): void {
        $data = $this->set_up();
        $checker = new timechecker();

        // Never logged in.
        // Suspended users without archive table entry are included.
        // $never = ["anonym9", "anonym10", "never_logged_in_1", "never_logged_in_2"];
        // $returnnever = $checker->get_never_logged_in();
        // $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returnnever), $never);

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

        set_config('suspendtime', 0.5, 'userstatus_timechecker');
        set_config('deletetime', 0.5, 'userstatus_timechecker');
        $newchecker = new timechecker();

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
    }*/

}
