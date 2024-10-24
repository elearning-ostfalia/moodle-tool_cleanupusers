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
#[CoversClass(\userstatus_ldapchecker\ldapchecker::class)]
class userstatus_ldapchecker_test extends \tool_cleanupusers\userstatus_base_test {

    protected function setup() : void {
        // set enabled plugin for running task
        set_config(CONFIG_ENABLED, "ldapchecker");
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD, 'userstatus_ldapchecker');
        set_config(CONFIG_DELETETIME, 365, 'userstatus_ldapchecker');
        $this->generator = advanced_testcase::getDataGenerator();
        $this->checker = $this->create_checker();
        $this->resetAfterTest(true);
    }

    protected function create_checker() {
        return new \userstatus_ldapchecker\ldapchecker(true);
    }

    public function typical_scenario_for_reactivation() : ?\stdClass {
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

    // TESTS

    // ---------------------------------------------
    // Suspend
    // ---------------------------------------------
    public function test_in_ldap_no_suspend() {
        $user = $this->create_test_user('username');
        $this->checker->fill_ldap_response_for_testing(["username" => 1]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_not_in_ldap_no_reactivate() {
        $user = $this->typical_scenario_for_reactivation();
        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);

        // different LDAP results without user's username
        $this->checker->fill_ldap_response_for_testing(["u1" => 1, "u2" => 1]);
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }
}