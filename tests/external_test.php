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
 * This is test for the external reactivation method.
 *
 * @package    tool_cleanupusers
 * @copyright  2025 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

require_once(__DIR__ . '/../classes/userstatuschecker.php');

define('AUTH_METHOD', 'email');
define('AUTH_METHOD_2', 'manual');
define('LAST_MONTH',    (time() - (DAYSECS * 30)));

// use tool_cleanupusers\userstatuschecker;
/**
 * External function submit_selected_courses_form_test.
 *
 * @package    tool_cleanupusers
 * @copyright  2025 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_dataprivacy\api
 */
final class external_test extends \externallib_advanced_testcase {

    protected $checker_login;
    protected $checker_course;

    // Each test starts with two archived users
    protected $user1;
    protected $user2;

    protected function setup(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        // set enabled plugin for running task
        set_config(CONFIG_ENABLED, "neverloginchecker,nocoursechecker");
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD, 'userstatus_neverloginchecker');
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD_2, 'userstatus_nocoursechecker');
        set_config(CONFIG_SUSPENDTIME, 10, 'userstatus_neverloginchecker');
        set_config(CONFIG_DELETETIME, 20, 'userstatus_neverloginchecker');
        set_config(CONFIG_SUSPENDTIME, 10, 'userstatus_nocoursechecker');
        set_config(CONFIG_DELETETIME, 20, 'userstatus_nocoursechecker');
        set_config('keepteachers', 1, 'userstatus_nocoursechecker');

        $this->checker_login = new \userstatus_neverloginchecker\neverloginchecker();
        $this->checker_course = new \userstatus_nocoursechecker\nocoursechecker();

        $generator = self::getDataGenerator();

        $timecreated = LAST_MONTH;
        $user1 = $generator->create_user(['username' => 'user1', 'auth' => AUTH_METHOD, 'timecreated' => $timecreated]);
        $user2 = $generator->create_user(['username' => 'user2', 'auth' => AUTH_METHOD_2, 'timecreated' => $timecreated]);
        $this->assertEquals($timecreated, $user1->timecreated);
        $this->assertEquals($timecreated, $user2->timecreated);

        // ensure that both checkers will have a suspension set
        $this->assertEquals(1, count($this->checker_login->get_to_suspend()));
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $this->checker_login->get_to_suspend()),
                [$user1->username]);
        $this->assertEquals(1, count($this->checker_course->get_to_suspend()));
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $this->checker_course->get_to_suspend()),
                [$user2->username]);

        // archive
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // check that both users are archived
        $this->assertEquals(0, count($this->checker_login->get_to_suspend()));
        $this->assertEquals(0, count($this->checker_course->get_to_suspend()));

        global $DB;
        // Emails are hidden
        $this->assertFalse($DB->get_record('user', ['email' => $user1->email]));
        $this->assertFalse($DB->get_record('user', ['email' => $user2->email]));

        $this->user1 = $user1;
        $this->user2 = $user2;
    }

    /**
     * @return \moodle_database
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function assertIsReactivated($user, $checker): void {
        $this->assertEquals(1, count($checker->get_to_suspend()));
        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $checker->get_to_suspend()),
                [$user->username]);
        global $DB;
        $this->assertNotFalse($DB->get_record('user', ['email' => $user->email]));
        $this->assertFalse($DB->get_record('tool_cleanupusers_archive', ['email' => $user->email]));
    }
    protected function assertIsNotReactivated($user, $checker): void {
        $this->assertEquals(0, count($checker->get_to_suspend()));
        global $DB;
        $this->assertFalse($DB->get_record('user', ['email' => $user->email]));
        $this->assertNotFalse($DB->get_record('tool_cleanupusers_archive', ['email' => $user->email]));
    }

    protected function execute_webservice($param) {
        // Call the external service function.
        $returnvalue = reactivate_users::execute($param);

        // We need to execute the return values cleaning process to simulate
        // the web service server.
        $returnvalue = \core_external\external_api::clean_returnvalue(
                reactivate_users::execute_returns(),
                $returnvalue
        );
        return $returnvalue;
    }
    /**
     * Scenario: User1 is suspended and can be reactivated
     * @return void
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_reactivate_user1(): void {
        $returnvalue = $this->execute_webservice([$this->user1->email]);

        $this->assertTrue(is_array($returnvalue));
        $this->assertEquals(1, count($returnvalue));
        $this->assertEquals($this->user1->email, $returnvalue[0]);

        // User1 is reacivated
        // => would be suspended immediately
        $this->assertIsReactivated($this->user1, $this->checker_login);
        // User2 is not reactivated
        $this->assertIsNotReactivated($this->user2, $this->checker_course);
    }

    /**
     * Scenario: User2 is suspended and can be reactivated
     * @return void
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_reactivate_user2(): void {
        $returnvalue = $this->execute_webservice([$this->user2->email]);

        $this->assertTrue(is_array($returnvalue));
        $this->assertEquals(1, count($returnvalue));
        $this->assertEquals($this->user2->email, $returnvalue[0]);

        // User2 is reacivated
        // => would be suspended immediately
        $this->assertIsReactivated($this->user2, $this->checker_course);
        // User1 is not reactivated
        $this->assertIsNotReactivated($this->user1, $this->checker_login);
    }

    public function test_reactivate_user1_and_user2(): void {
        $returnvalue = $this->execute_webservice([$this->user1->email, $this->user2->email]);

        $this->assertTrue(is_array($returnvalue));
        $this->assertEquals(2, count($returnvalue));
        $this->assertEquals($this->user1->email, $returnvalue[0]);
        $this->assertEquals($this->user2->email, $returnvalue[1]);

        // User1 und user2 are reacivated
        // => would be suspended immediately
        $this->assertIsReactivated($this->user1, $this->checker_login);
        // User2 is not reactivated
        $this->assertIsReactivated($this->user2, $this->checker_course);
    }

    public function test_reactivate_unknown_user(): void {
        $returnvalue = $this->execute_webservice(['test@moodle.org']);

        $this->assertTrue(is_array($returnvalue));
        $this->assertEquals(0, count($returnvalue));

        // User1 und user2 remain in old state
        $this->assertIsNotReactivated($this->user1, $this->checker_login);
        $this->assertIsNotReactivated($this->user2, $this->checker_course);
    }

    public function test_reactivate_empty_param(): void {
        $returnvalue = $this->execute_webservice(['']);

        $this->assertTrue(is_array($returnvalue));
        $this->assertEquals(0, count($returnvalue));

        // User1 und user2 remain in old state
        $this->assertIsNotReactivated($this->user1, $this->checker_login);
        $this->assertIsNotReactivated($this->user2, $this->checker_course);
    }

    /**
     * Scenario: reactivate user who is not suspended
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_reactivate_user1_twice(): void {
        $returnvalue = $this->execute_webservice([$this->user1->email]);

        $this->assertTrue(is_array($returnvalue));
        $this->assertEquals(1, count($returnvalue));
        $this->assertEquals($this->user1->email, $returnvalue[0]);

        // User1 is reacivated
        // => would be suspended immediately
        $this->assertIsReactivated($this->user1, $this->checker_login);
        // User2 is not reactivated
        $this->assertIsNotReactivated($this->user2, $this->checker_course);

        $returnvalue = $this->execute_webservice([$this->user1->email]);
        $this->assertTrue(is_array($returnvalue));
        $this->assertEquals(0, count($returnvalue));

        $this->assertIsReactivated($this->user1, $this->checker_login);
        // User2 is not reactivated
        $this->assertIsNotReactivated($this->user2, $this->checker_course);
    }
}
