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
use PHPUnit\Framework\Attributes\CoversClass;

require_once(__DIR__.'/../../../tests/userstatus_base.php');

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
class userstatus_ldapchecker_test extends \tool_cleanupusers\userstatus_base {

    /**
     * Enables the ldapchecker, sets the authentication method and the deletion time
     * and creates the checker in testing mode (no connection to an LDAP server).
     *
     * @return void
     */
    protected function setup(): void {
        // set enabled plugin for running task
        set_config(CONFIG_ENABLED, "ldapchecker");
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD, 'userstatus_ldapchecker');
        set_config(CONFIG_DELETETIME, 365, 'userstatus_ldapchecker');
        $this->generator = advanced_testcase::getDataGenerator();
        $this->checker = $this->create_checker();
        $this->resetAfterTest(true);
    }

    /**
     * Sets a config value and recreates the checker (see parent).
     * The faked LDAP response of the old checker is copied to the new checker
     * so that changing the configuration does not reset the LDAP users.
     *
     * @param string $name
     * @param mixed $value
     * @param string|null $plugin
     * @return void
     */
    protected function set_config($name, $value, $plugin = null) {
        $oldchecker = $this->checker;
        parent::set_config($name, $value, $plugin);
        if (isset($oldchecker)) {
            $myClassReflection = new ReflectionClass(get_class($oldchecker));
            $secret = $myClassReflection->getProperty('lookup');
            $secret->setAccessible(true);
            $ldaplist = $secret->getValue($oldchecker);
            $this->checker->fill_ldap_response_for_testing($ldaplist);
        }
    }

    /**
     * Creates the ldapchecker in testing mode.
     *
     * @return \userstatus_ldapchecker\ldapchecker
     */
    protected function create_checker() {
        return new \userstatus_ldapchecker\ldapchecker(true);
    }

    /**
     * Typical scenario for reactivation:
     * user is missing in LDAP and gets archived by the cron job.
     * Afterwards the user is added to the LDAP response again.
     *
     * @return \stdClass|null archived user who shall be reactivated
     */
    public function typical_scenario_for_reactivation(): ?\stdClass {
        $user = $this->create_test_user('username');
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        $this->checker->fill_ldap_response_for_testing(["username" => $user->username]);
        return $user;
    }

    /**
     * Typical scenario for suspension:
     * user is not enrolled in any course and missing in LDAP
     * (no LDAP response is set).
     *
     * @return \stdClass user who shall be suspended
     */
    public function typical_scenario_for_suspension(): \stdClass {
        return $this->create_test_user('username');
    }

    // TESTS

    // ---------------------------------------------
    // Suspend
    // ---------------------------------------------

    /**
     * Precondition: user is found in LDAP and not enrolled in any course
     * * => expect user not to be suspended as the user is still in LDAP
     * @return void
     */
    public function test_in_ldap_not_enrolled_no_suspend() {
        $user = $this->create_test_user('username');
        $this->checker->fill_ldap_response_for_testing(["username" => 1]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    /**
     * Configuration: suspend_only_unenrolled is not set (default)
     * Precondition: user is missing in LDAP and enrolled in a course
     * => expect user to be suspended as enrolments are not considered
     *
     * @return void
     */
    public function test_not_in_ldap_enrolled_as_student_suspend() {
        $user = $this->create_test_user('username');
        $course = $this->generator->create_course();
        $this->generator->enrol_user($user->id, $course->id, 'student');

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    // ---------------------------------------------
    // Reactivate
    // ---------------------------------------------

    /**
     * Precondition: user is archived and found in LDAP again
     * => expect user to be reactivated.
     * Then the LDAP response changes and the user is missing again
     * => expect user not to be reactivated
     *
     * @return void
     */
    public function test_not_in_ldap_no_reactivate() {
        $user = $this->typical_scenario_for_reactivation();
        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);

        // different LDAP results without user's username
        $this->checker->fill_ldap_response_for_testing(["u1" => 1, "u2" => 1]);
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }
}
