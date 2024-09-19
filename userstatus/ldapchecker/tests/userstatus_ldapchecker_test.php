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
 * The class contains a test script for the moodle userstatus_ldapchecker
 *
 * @package    userstatus_ldapchecker
 * @category   phpunit
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use userstatus_ldapchecker\ldapchecker;

require_once(__DIR__.'/../../../tests/userstatus_base_test.php');

// use advanced_testcase;

/**
 * The class contains a test script for the moodle userstatus_ldapchecker
 *
 * @package    userstatus_ldapchecker
 * @category   phpunit
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstatus_ldapchecker_test extends \tool_cleanupusers\userstatus_base_test {

    protected function setup() : void {
        // set enabled plugin for running task
        set_config('userstatus_plugins_enabled', "ldapchecker");
        set_config('auth_method', AUTH_METHOD, 'userstatus_ldapchecker');
        set_config('deletetime', 365, 'userstatus_ldapchecker');
        $this->generator = advanced_testcase::getDataGenerator();
        $this->checker = new \userstatus_ldapchecker\ldapchecker(true);
        $this->resetAfterTest(true);
    }

    public function typical_scenario_for_reactivation() : \stdClass {
        $user = $this->create_test_user('username');
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        $this->checker->fill_ldap_response_for_testing(["username" => $user->username]);
        return $user;
    }

    public function typical_scenario_for_suspension() : \stdClass {
        return $this->create_test_user('username');
    }

/*
    protected function set_up() {
        $config = get_config('userstatus_ldapchecker');
        $config->deletetime = 356; // Set time for deleting users in days

        $generator = $this->getDataGenerator()->get_plugin_generator('userstatus_ldapchecker');
        $data = $generator->test_create_preparation();
        $this->resetAfterTest(true);
        return $data;
    }
*/
    // TESTS

    // ---------------------------------------------
    // Suspend
    // ---------------------------------------------
    public function test_not_in_ldap_suspend() {
        $user = $this->create_test_user('username');
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    public function test_other_auth_no_suspend() {
        $this->create_test_user('username', ['auth' => 'email']);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_in_ldap_no_suspend() {
        $this->create_test_user('username');
        $this->checker->fill_ldap_response_for_testing(["username" => 1]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    // ---------------------------------------------
    // Reactivate
    // ---------------------------------------------
    public function test_not_in_ldap_then_in_ldap_reactivate() {
        $user = $this->typical_scenario_for_reactivation();
        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);
    }


    /**
     * Function to test the class ldapchecker.
     *
     * @see ldapchecker
     */
    /*
    public function test_locallib() {
        $deleteduser_by_plugin = $this->set_up();

        // Testing is set to true which means that it does not try to connect to LDAP.
        $myuserstatuschecker = new ldapchecker(true);

        $myuserstatuschecker->fill_ldap_response_for_testing(array( "tu_id_1" => 1,
                                                                    "tu_id_2" => 1,
                                                                    "tu_id_3" => 1,
                                                                    "tu_id_4" => 1,
                                                                ));

        // User to suspend
        $returnsuspend = $myuserstatuschecker->get_to_suspend();
        $this->assertEquals(1, count($returnsuspend));
        $this->assertEquals("to_suspend", reset($returnsuspend)->username);

        // Add user which should be reactivated
        $myuserstatuschecker->fill_ldap_response_for_testing(array(
            "tu_id_1" => 1,
            "tu_id_2" => 1,
            "tu_id_3" => 1,
            "tu_id_4" => 1,
            "to_reactivate" => 1,
        ));
        $returntoreactivate = $myuserstatuschecker->get_to_reactivate();
        $this->assertEquals(1, count($returntoreactivate));
        $this->assertEquals("to_reactivate", reset($returntoreactivate)->username);
        $this->assertEquals("to_reactivate", end($returntoreactivate)->username);

        $returndelete = $myuserstatuschecker->get_to_delete();
        $this->assertEquals("to_delete_manually", reset($returndelete)->username);
        $this->assertEquals($deleteduser_by_plugin->id, end($returndelete)->id);

        $this->resetAfterTest(true);

    }
    */

}