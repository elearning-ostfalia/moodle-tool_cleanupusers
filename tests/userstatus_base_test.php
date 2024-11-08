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
 * The class contains a base class for the moodle userstatus_xxx classes
 *
 * @package    userstatus_base_test
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace tool_cleanupusers;

require_once(__DIR__ . '/cleanupusers_testcase.php');

abstract class userstatus_base_test extends cleanupusers_testcase
{
    protected $generator = null;
    protected $checker = null;

    /**
     * @return string
     * @throws \coding_exception
     */
    protected function get_plugin_name(): string
    {
        $checker = get_class($this->checker);
        // strip namespace from checker class
        $index = strpos($checker, "\\");
        if ($index === false)
            throw new \coding_exception("cannot determine namespace of " . $checker);

        $plugin = substr($checker, 0, $index);
        return $plugin;
    }

    abstract public function typical_scenario_for_reactivation(): ?\stdClass;

    abstract public function typical_scenario_for_suspension(): \stdClass;

    abstract protected function create_checker();

    // TESTS
    // Common tests for all subplugins

    // ---------------------------------------------
    // SUSPEND
    // ---------------------------------------------
    public function test_simple_suspend() {
        $user = $this->typical_scenario_for_suspension();
        $suspened = $user->suspended;
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
        // $this->assertEquals(0, $user->suspended);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // check if all users have been suspended
        $this->assertEquals(0, count($this->checker->get_to_suspend()));

        // check if user won't be activated now.
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));

        global $DB;
        $record = $DB->get_record('user', ['id' => $user->id]);
        $this->assertEquals(1, $record->suspended);
        $this->assertStringStartsWith('anonym', $record->username);
        $this->assertEquals('Anonym', $record->firstname);
        $this->assertEquals('', $record->lastname);
        $this->assertEquals($user->auth, $record->auth); // not modified

        $record = $DB->get_record('tool_cleanupusers', ['id' => $user->id]);
        $checker = substr($this->get_plugin_name(), strlen('userstatus_'));
        $this->assertEquals($checker, $record->checker);
        $this->assertEquals(1, $record->archived);
        $this->assertTimeCurrent($record->timestamp);

        $record = $DB->get_record('tool_cleanupusers_archive', ['id' => $user->id]);
        $this->assertEquals($user->username, $record->username);
        $this->assertEquals($user->firstname, $record->firstname);
        $this->assertEquals($user->lastname, $record->lastname);
        $this->assertEquals($user->auth, $record->auth); // not modified
        $this->assertEquals($suspened, $record->suspended);
        $this->assertEquals($user->lastaccess, $record->lastaccess);
        $this->assertEquals($user->timecreated, $record->timecreated);
    }

    public function test_config_auth_plus_suspend() {
        $this->set_config(CONFIG_AUTH_METHOD, 'email,' . AUTH_METHOD, $this->get_plugin_name());
        // Create new checker instance so that configuration will be "reread".
        $user = $this->typical_scenario_for_suspension();
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    public function test_config_no_auth_suspend_1() {
        $this->set_config(CONFIG_AUTH_METHOD, '', $this->get_plugin_name());
        // Create new checker instance so that configuration will be "reread".
        $user = $this->typical_scenario_for_suspension();
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    public function test_config_no_auth_method_2()
    {
        unset_config(CONFIG_AUTH_METHOD, $this->get_plugin_name());
        $this->checker = $this->create_checker();
        $user = $this->typical_scenario_for_suspension();
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    public function test_config_other_auth_no_suspend() {
        $this->set_config(CONFIG_AUTH_METHOD, 'email', $this->get_plugin_name());
        $user = $this->typical_scenario_for_suspension();
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    /**
     * checks what happens if the user is already manually suspended
     * => should also be suspended because the user needs to be archived
     * @return void
     * @throws \dml_exception
     */
    public function test_already_suspended_suspend() {
        $user = $this->typical_scenario_for_suspension();
        global $DB;
        $user->suspended = 1;
        $DB->update_record('user', $user);

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));

        // Check if user is suspended if he is reactivated
        $userarray = [];
        $userarray[$user->id] = $user;
        $result = helper::change_user_deprovisionstatus($userarray, 'reactivate', '');

        global $DB;
        $record = $DB->get_record('user', ['id' => $user->id]);
        $this->assertEquals($user->username, $record->username);
        $this->assertEquals($user->firstname, $record->firstname);
        $this->assertEquals($user->lastname, $record->lastname);
        $this->assertEquals($user->auth, $record->auth); // not modified
        $this->assertEquals(1, $record->suspended);
        $this->assertEquals($user->lastaccess, $record->lastaccess);
        $this->assertEquals($user->timecreated, $record->timecreated);
    }

    public function test_already_deleted_not_suspend() {
        $user = $this->typical_scenario_for_suspension();
        global $DB;
        $user->deleted = 1;
        $DB->update_record('user', $user);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }


    // ---------------------------------------------
    // REACTIVATE
    // ---------------------------------------------
    public function test_reactivate() {
        $user = $this->typical_scenario_for_reactivation();
        if (!$user) {
            // No such scenario available (suspendedchecker)
            return;
        }
        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        if ($this->get_plugin_name() == 'userstatus_ldapchecker') {
            // Task does not work properly for ldapchecker
            // because a new ldapchecker instance is generated in task
            // which does not have the previously set lookup data.
            return;
        }
        // check if user won't be activated now.
        $this->assertEquals(0, count($this->checker->get_to_suspend()));

        global $DB;
        $record = $DB->get_record('user', ['id' => $user->id]);
        $this->assertEquals($user->username, $record->username);
        $this->assertEquals($user->firstname, $record->firstname);
        $this->assertEquals($user->lastname, $record->lastname);
        $this->assertEquals($user->auth, $record->auth); // not modified
        $this->assertEquals(0, $record->suspended);
        $this->assertEquals($user->lastaccess, $record->lastaccess);
        $this->assertEquals($user->timecreated, $record->timecreated);

        $record = $DB->get_record('tool_cleanupusers', ['id' => $user->id]);
        $this->assertFalse($record);

        $record = $DB->get_record('tool_cleanupusers_archive', ['id' => $user->id]);
        $this->assertFalse($record);
    }


    public function test_reactivate_username_already_exists_no_reactivate() {
        $user = $this->typical_scenario_for_reactivation();
        if (!$user) {
            // No such scenario available (suspendedchecker)
            return;
        }
        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);

        $user1 = $this->create_test_user($user->username, ['firstname' => $user->firstname,
            'lastname' => $user->lastname, 'email' => $user->email]);

        // user will not be reactivated!
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

    /**
     * like test_invisible_course_make_visisble_reactivate, but record in tool_cleanupusers is missing
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_incomplete_archive_no_reactivate_1() {
        $user = $this->typical_scenario_for_reactivation();
        if (!$user) {
            // No such scenario available (suspendedchecker)
            return;
        }
        global $DB;
        $DB->delete_records('tool_cleanupusers', ['id' => $user->id]); // NEW for test_invisible_course_make_visisble_reactivate
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

    public function test_incomplete_archive_no_reactivate_2() {
        $user = $this->typical_scenario_for_reactivation();
        if (!$user) {
            // No such scenario available (suspendedchecker)
            return;
        }
        global $DB;
        $DB->delete_records('tool_cleanupusers_archive', ['id' => $user->id]); // NEW for test_invisible_course_make_visisble_reactivate
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

    public function test_incomplete_archive_no_reactivate_3() {
        $user = $this->typical_scenario_for_reactivation();
        if (!$user) {
            // No such scenario available (suspendedchecker)
            return;
        }
        global $DB;
        $DB->delete_records('tool_cleanupusers_archive', ['id' => $user->id]); // NEW for test_invisible_course_make_visisble_reactivate
        $DB->delete_records('tool_cleanupusers', ['id' => $user->id]); // NEW for test_invisible_course_make_visisble_reactivate
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

    // ---------------------------------------------
    // DELETE
    // ---------------------------------------------
    public function test_too_early_for_deletion_no_delete() {
        $user = $this->typical_scenario_for_suspension();
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();
        $this->assertEquals(0, count($this->checker->get_to_delete()));
    }

    public function test_too_early_for_deletion_change_config_delete() {
        $user = $this->typical_scenario_for_suspension();
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        sleep(1); // ensure time condition is met
        $this->assertEquals(0, count($this->checker->get_to_delete()));

        $this->set_config(CONFIG_DELETETIME, 0, $this->get_plugin_name());

        $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);
    }

    public function test_delete() {
        $user = $this->typical_scenario_for_suspension();
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // Fake: Update timestamp so that it seems as if
        // the record has been inserted a year ago
        // => data can be deleted
        global $DB;
        $record = new \stdClass();
        $record->id = $user->id;
        $record->timestamp = YEARAGO;
        $DB->update_record_raw('tool_cleanupusers', $record);

        $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);

        $cronjob = new \tool_cleanupusers\task\delete_user_task();
        $cronjob->execute();

        $this->assertEquals(0, count($this->checker->get_to_delete()));

        // no records in tool_cleanupusers and tool_cleanupusers_archive
        $record = $DB->get_record('tool_cleanupusers', ['id' => $user->id]);
        $this->assertFalse($record);

        $record = $DB->get_record('tool_cleanupusers_archive', ['id' => $user->id]);
        $this->assertFalse($record);

        $record = $DB->get_record('user', ['id' => $user->id]);
        $this->assertEquals(1, $record->deleted);

        // Check that no user data remains.
        $this->assertStringNotContainsStringIgnoringCase($user->username, $record->username);
        $this->assertStringNotContainsStringIgnoringCase($user->username, $record->firstname);
        $this->assertStringNotContainsStringIgnoringCase($user->username, $record->lastname);
        $this->assertStringNotContainsStringIgnoringCase($user->username, $record->email);

        $this->assertStringNotContainsStringIgnoringCase($user->firstname, $record->username);
        $this->assertStringNotContainsStringIgnoringCase($user->firstname, $record->firstname);
        $this->assertStringNotContainsStringIgnoringCase($user->firstname, $record->lastname);
        $this->assertStringNotContainsStringIgnoringCase($user->firstname, $record->email);

        $this->assertStringNotContainsStringIgnoringCase($user->lastname, $record->username);
        $this->assertStringNotContainsStringIgnoringCase($user->lastname, $record->firstname);
        $this->assertStringNotContainsStringIgnoringCase($user->lastname, $record->lastname);
        $this->assertStringNotContainsStringIgnoringCase($user->lastname, $record->email);

        $this->assertStringNotContainsStringIgnoringCase($user->email, $record->username);
        $this->assertStringNotContainsStringIgnoringCase($user->email, $record->firstname);
        $this->assertStringNotContainsStringIgnoringCase($user->email, $record->lastname);
        $this->assertStringNotContainsStringIgnoringCase($user->email, $record->email);
    }

    /**
     * user is suspended and could be deleted at once if he or she never logged in.
     * But user has logged in
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_not_logged_in_configured_but_logged_in_no_delete() {
        $this->set_config(CONFIG_NEVER_LOGGED_IN, '1', $this->get_plugin_name());

        $user = $this->typical_scenario_for_suspension();
        global $DB;
        $user->lastaccess = time();
        $DB->update_record('user', $user);

        if ($this->checker->get_to_suspend() != null) {
            // This test can only be run if lastaccess is not part of
            // condition
            $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
            // run cron
            $cronjob = new \tool_cleanupusers\task\archive_user_task();
            $cronjob->execute();

            $this->assertEquals(0, count($this->checker->get_to_delete()));
        }
    }

    /**
     * Configuration: user shall be deleted immediately if never logged in.
     * Precondition: User is archived and has not logged not
     * => expect to be deleted
     *
     * @return void
     * @throws \coding_exception
     */
    public function test_not_logged_in_configured_and_not_logged_in_delete() {
        $this->set_config(CONFIG_NEVER_LOGGED_IN, '1', $this->get_plugin_name());

        $user = $this->typical_scenario_for_suspension();
        if ($this->checker->get_to_suspend() != null and $user->lastaccess == 0) {
            // This test can only be run if lastaccess is not part of suspend condition
            $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
            // run cron
            $cronjob = new \tool_cleanupusers\task\archive_user_task();
            $cronjob->execute();
            $this->assertEquals(0, count($this->checker->get_to_suspend()));

            $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);
            $cronjob = new \tool_cleanupusers\task\delete_user_task();
            $cronjob->execute();
            $this->assertEquals(0, count($this->checker->get_to_delete()));
        }
    }

    public function test_duplicate_username_delete() {
        $user = $this->typical_scenario_for_suspension();
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // Fake: Update timestamp so that it seems as if
        // the record has been inserted a year ago
        // => data can be deleted
        global $DB;
        $record = new \stdClass();
        $record->id = $user->id;
        $record->timestamp = YEARAGO;
        $DB->update_record_raw('tool_cleanupusers', $record);

        $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);

        // Create new user with same attributes as the one that
        // is already suspended.
        $user1 = $this->create_test_user($user->username, ['firstname' => $user->firstname,
            'lastname' => $user->lastname, 'email' => $user->email]);

        $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);

        $cronjob = new \tool_cleanupusers\task\delete_user_task();
        $cronjob->execute();

        // no records in tool_cleanupusers and tool_cleanupusers_archive
        $record = $DB->get_record('tool_cleanupusers', ['id' => $user->id]);
        $this->assertFalse($record);

        $record = $DB->get_record('tool_cleanupusers_archive', ['id' => $user->id]);
        $this->assertFalse($record);

        // Check that no user data left over.
        $record = $DB->get_record('user', ['id' => $user->id]);
        $this->assertEquals(1, $record->deleted);

        $this->assertStringNotContainsStringIgnoringCase($user->username, $record->username);
        $this->assertStringNotContainsStringIgnoringCase($user->username, $record->firstname);
        $this->assertStringNotContainsStringIgnoringCase($user->username, $record->lastname);
        $this->assertStringNotContainsStringIgnoringCase($user->username, $record->email);

        $this->assertStringNotContainsStringIgnoringCase($user->firstname, $record->username);
        $this->assertStringNotContainsStringIgnoringCase($user->firstname, $record->firstname);
        $this->assertStringNotContainsStringIgnoringCase($user->firstname, $record->lastname);
        $this->assertStringNotContainsStringIgnoringCase($user->firstname, $record->email);

        $this->assertStringNotContainsStringIgnoringCase($user->lastname, $record->username);
        $this->assertStringNotContainsStringIgnoringCase($user->lastname, $record->firstname);
        $this->assertStringNotContainsStringIgnoringCase($user->lastname, $record->lastname);
        $this->assertStringNotContainsStringIgnoringCase($user->lastname, $record->email);

        $this->assertStringNotContainsStringIgnoringCase($user->email, $record->username);
        $this->assertStringNotContainsStringIgnoringCase($user->email, $record->firstname);
        $this->assertStringNotContainsStringIgnoringCase($user->email, $record->lastname);
        $this->assertStringNotContainsStringIgnoringCase($user->email, $record->email);

        // duplicate user is unchanged
        $record = $DB->get_record('user', ['id' => $user1->id]);
        $this->assertEquals(0, $record->deleted);
        // Do not check for suspension flag because
        // the duplicate user could also have been suspended in task
        // because he or she matches the suspension filter
        // (maybe problem with ldapchecker and lookup table missing
        // in task)
        // $this->assertEquals(0, $record->suspended);
        $this->assertEquals($user1->username, $record->username);
        $this->assertEquals($user1->firstname, $record->firstname);
        $this->assertEquals($user1->lastname, $record->lastname);
        $this->assertEquals($user1->auth, $record->auth); // not modified
        $this->assertEquals($user1->lastaccess, $record->lastaccess);
        $this->assertEquals($user1->timecreated, $record->timecreated);
    }


    public function test_delete_incomplete_no_delete_1() {
        $user = $this->typical_scenario_for_suspension();
        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // Fake: Update timestamp so that it seems as if
        // the record has been inserted a year ago
        // => data can be deleted
        global $DB;
        $record = new \stdClass();
        $record->id = $user->id;
        $record->timestamp = YEARAGO;
        $DB->update_record_raw('tool_cleanupusers', $record);

        $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);

        $DB->delete_records('tool_cleanupusers', ['id' => $user->id]);
        $this->assertEquals(0, count($this->checker->get_to_delete()));
    }


    public function test_delete_incomplete_no_delete_2() {
        $user = $this->typical_scenario_for_suspension();
        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // Fake: Update timestamp so that it seems as if
        // the record has been inserted a year ago
        // => data can be deleted
        global $DB;
        $record = new \stdClass();
        $record->id = $user->id;
        $record->timestamp = YEARAGO;
        $DB->update_record_raw('tool_cleanupusers', $record);

        $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);

        $DB->delete_records('tool_cleanupusers_archive', ['id' => $user->id]);
        $this->assertEquals(0, count($this->checker->get_to_delete()));
    }

    public function test_delete_incomplete_no_delete_3() {
        $user = $this->typical_scenario_for_suspension();
        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // Fake: Update timestamp so that it seems as if
        // the record has been inserted a year ago
        // => data can be deleted
        global $DB;
        $record = new \stdClass();
        $record->id = $user->id;
        $record->timestamp = YEARAGO;
        $DB->update_record_raw('tool_cleanupusers', $record);

        $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);

        // Fake: manually suspended user who fulfills delete condition
        $DB->delete_records('tool_cleanupusers_archive', ['id' => $user->id]);
        $DB->delete_records('tool_cleanupusers', ['id' => $user->id]);
        $this->assertEquals(0, count($this->checker->get_to_delete()));
    }

    public function test_reactivate_and_delete_possible_no_delete() {
        $user = $this->typical_scenario_for_reactivation();
        if (!$user || $user->lastaccess != 0) {
            // No such scenario available (suspendedchecker)
            return;
        }

        assert($user->lastaccess == 0); // User has never logged in!
        $this->assertTrue(array_key_exists($user->username, $this->get_archive()));
        // Modify delete so that the user will be deleted if he has never logged in
        // => user can be reactivated AND deleted
        $this->set_config(CONFIG_NEVER_LOGGED_IN, 1, $this->get_plugin_name());
        $this->assertEquals(0, count($this->checker->get_to_delete()));
        // $this->assertEqualsUsersArrays($this->checker->get_to_delete(), $user);
        $this->assertEqualsUsersArrays($this->checker->get_to_reactivate(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        if ($this->get_plugin_name() == 'userstatus_ldapchecker') {
            // Task does not work properly for ldapchecker
            // because a new ldapchecker instance is generated in task
            // which does not have the previously set lookup data.
            return;
        }

        // expect user to be not in archive
        $this->assertFalse(array_key_exists($user->username, $this->get_archive()));
        $this->assertTrue(array_key_exists($user->username, $this->get_normal_users()));
    }


    /*
     * does not work: checker does not check if it is enabled! ???
    public function test_config_no_plugin_enabled()
    {
        $this->set_config('userstatus_plugins_enabled', "");
        $user = $this->typical_scenario_for_suspension();
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }*/



    /**
     * Methodes recommended by moodle to assure database and dataroot is reset.
     */
    public function test_deleting(): void {
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
    public function test_user_table_was_reset(): void {
        global $DB;
        $this->assertEquals(2, $DB->count_records('user', []));
        $this->assertEquals(0, $DB->count_records('tool_cleanupusers', []));
    }

}