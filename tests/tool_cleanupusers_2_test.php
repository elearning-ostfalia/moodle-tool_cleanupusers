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
 * Test script for the moodle tool_cleanupusers plugin.
 *
 * @package    tool_cleanupusers
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers;

require_once(__DIR__ . '/cleanupusers_testcase.php');

define('AUTH_METHOD_2', 'manual');

use advanced_testcase;

/**
 * Testcase class for executing phpunit test for the moodle tool_cleanupusers plugin.
 *
 * @package    tool_cleanupusers
 * @group      tool_cleanupusers
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_cleanupusers_2_test extends cleanupusers_testcase {
    private $checker_login;
    private $checker_course;
    private $mailsink;
    private $eventsink;
    private $timestamp;

    protected function setup(): void {
        $this->generator = advanced_testcase::getDataGenerator();
        $this->resetAfterTest(true);

        // Set up mail configuration.
        unset_config('noemailever');
        $this->mailsink = $this->redirectEmails();

        $this->timestamp = time();

        $this->eventsink = $this->redirectEvents();

        // set enabled plugin for running task
        set_config(CONFIG_ENABLED, "neverloginchecker,nocoursechecker");
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD, 'userstatus_neverloginchecker');
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD_2, 'userstatus_nocoursechecker');
        set_config(CONFIG_SUSPENDTIME, 10, 'userstatus_neverloginchecker');
        set_config(CONFIG_DELETETIME, 20, 'userstatus_neverloginchecker');
        set_config(CONFIG_SUSPENDTIME, 10, 'userstatus_nocoursechecker');
        set_config(CONFIG_DELETETIME, 20, 'userstatus_nocoursechecker');
        set_config('keepteachers', 1, 'userstatus_nocoursechecker');
        // set_config('waitingperiod', 10, 'userstatus_nocoursechecker');

        $this->checker_login = new \userstatus_neverloginchecker\neverloginchecker();
        $this->checker_course = new \userstatus_nocoursechecker\nocoursechecker();
    }

    protected function set_config($name, $value, $plugin = null) {
        set_config($name, $value, $plugin);
        // recreate checker in order to read new config
        $this->checker_login = new \userstatus_neverloginchecker\neverloginchecker();
        $this->checker_course = new \userstatus_nocoursechecker\nocoursechecker();
    }

    public function test_csv_output() {
        $path = '/tmp/phpunit/cleanup_users';
        set_config(CONFIG_LOG_FOLDER, $path, 'tool_cleanupusers');

        $user11 = $this->create_test_user('user11', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD]);
        $user12 = $this->create_test_user('user12', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD]);
        $user13 = $this->create_test_user('user13', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD]);

        $user21 = $this->create_test_user('user21', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD_2]);
        $user22 = $this->create_test_user('user22', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD_2]);
        $user23 = $this->create_test_user('user23', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD_2]);
        $user24 = $this->create_test_user('user24', ['timecreated' => LAST_MONTH, 'lastaccess' => YESTERDAY, 'auth' => AUTH_METHOD_2]);

        // ensure that both checkers will have a suspension set
        $this->assertEquals(3, count($this->checker_login->get_to_suspend()));
        $this->assertEquals(4, count($this->checker_course->get_to_suspend()));

        // archive
        $cronjob = new task\archive_user_task();
        $cronjob->execute();

        // check csv file
        $csvContent = $this->readCsv($path);

        $this->assertEquals(7, count($csvContent));
        $this->assertTrue(array_key_exists($user11->id, $csvContent));
        $this->assertTrue(array_key_exists($user12->id, $csvContent));
        $this->assertTrue(array_key_exists($user13->id, $csvContent));

        $this->assertTrue(array_key_exists($user21->id, $csvContent));
        $this->assertTrue(array_key_exists($user22->id, $csvContent));
        $this->assertTrue(array_key_exists($user23->id, $csvContent));
        $this->assertTrue(array_key_exists($user24->id, $csvContent));

        // check mails
        $this->checkMails(7);

        // check event
        $this->checkEvents();

    }


    /**
     * Tests that only users are suspended by enabled plugins
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_suspend_only_users_from_enabled_plugins() {
        $user1 = $this->create_test_user('user1', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD]);
        $user2 = $this->create_test_user('user2', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD_2]);

        // ensure that both checkers will have a suspension set
        $this->assertEqualsUsersArrays($this->checker_login->get_to_suspend(), $user1, 'neverloginchecker');
        $this->assertEqualsUsersArrays($this->checker_course->get_to_suspend(), $user2, 'nocoursechecker');

        // disable course checker
        set_config(CONFIG_ENABLED, "neverloginchecker");

        // archive
        $cronjob = new task\archive_user_task();
        $cronjob->execute();

        // check that only $user1 is archived
        $this->assertTrue(array_key_exists($user1->username, $this->get_archive()));
        $this->assertFalse(array_key_exists($user2->username, $this->get_archive()));
    }

    /**
     * Tests that only users are reactivated by enabled plugins
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_reactivate_only_by_enabled() {
        $user1 = $this->create_test_user('user1', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD]);
        $user2 = $this->create_test_user('user2', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD_2]);

        // ensure that both checkers will have a suspension set
        $this->assertEqualsUsersArrays($this->checker_login->get_to_suspend(), $user1, 'neverloginchecker');
        $this->assertEqualsUsersArrays($this->checker_course->get_to_suspend(), $user2, 'nocoursechecker');

        // archive
        $cronjob = new task\archive_user_task();
        $cronjob->execute();

        // check that both users are archived
        $this->assertTrue(array_key_exists($user1->username, $this->get_archive()));
        $this->assertTrue(array_key_exists($user2->username, $this->get_archive()));

        $this->set_config(CONFIG_SUSPENDTIME, 100, 'userstatus_neverloginchecker');

        $course = $this->generator->create_course();
        $this->generator->enrol_user($user2->id, $course->id);

        // ensure that both checkers will have a suspension set
        $this->assertEqualsUsersArrays($this->checker_login->get_to_reactivate(), $user1, 'neverloginchecker');
        $this->assertEqualsUsersArrays($this->checker_course->get_to_reactivate(), $user2, 'nocoursechecker');

        // disable course checker
        $this->set_config(CONFIG_ENABLED, "neverloginchecker");

        $cronjob->execute();

        // check that only $user2 is archived (
        $this->assertFalse(array_key_exists($user1->username, $this->get_archive()));
        $this->assertTrue(array_key_exists($user2->username, $this->get_archive()));
    }

    /**
     * Tests that only users are deleted by enabled plugins
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_delete_only_by_enabled() {
        $user1 = $this->create_test_user('user1', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD]);
        $user2 = $this->create_test_user('user2', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD_2]);

        // ensure that both checkers will have a suspension set
        $this->assertEqualsUsersArrays($this->checker_login->get_to_suspend(), $user1, 'neverloginchecker');
        $this->assertEqualsUsersArrays($this->checker_course->get_to_suspend(), $user2, 'nocoursechecker');

        // archive
        $cronjob = new task\archive_user_task();
        $cronjob->execute();

        // check that both users are archived
        $this->assertTrue(array_key_exists($user1->username, $this->get_archive()));
        $this->assertTrue(array_key_exists($user2->username, $this->get_archive()));

        $this->set_config(CONFIG_NEVER_LOGGED_IN, 1, 'userstatus_nocoursechecker');
        $this->set_config(CONFIG_NEVER_LOGGED_IN, 1, 'userstatus_neverloginchecker');

        // ensure that both checkers will have a suspension set
        $this->assertEqualsUsersArrays($this->checker_login->get_to_delete(), $user1, 'neverloginchecker');
        $this->assertEqualsUsersArrays($this->checker_course->get_to_delete(), $user2, 'nocoursechecker');

        // disable course checker
        $this->set_config(CONFIG_ENABLED, "neverloginchecker");

        $cronjob = new task\delete_user_task();
        $cronjob->execute();

        // check that only $user2 is archived
        $this->assertFalse(array_key_exists($user1->username, $this->get_archive()));
        $this->assertTrue(array_key_exists($user2->username, $this->get_archive()));
    }

    public function test_archive_all_disabled()
    {
        $user1 = $this->create_test_user('user1', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD]);
        $user2 = $this->create_test_user('user2', ['timecreated' => LAST_MONTH, 'auth' => AUTH_METHOD_2]);

        $this->set_config(CONFIG_ENABLED, "");

        // archive
        $cronjob = new task\archive_user_task();
        $cronjob->execute();

        $this->assertEquals(0, count($this->get_archive()));

        // archive
        $cronjob = new task\delete_user_task();
        $cronjob->execute();

        $this->assertEquals(0, count($this->get_archive()));
    }

    /**
     * @param string $path
     * @return void
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path . '/archived_users_' . date("d_m_Y") . '.csv', "r");
        $this->assertNotFalse($handle);

        $firstline = fgetcsv($handle, 1000, ";");
        // var_dump($firstline);
        $this->assertNotFalse($firstline);

        $csvcontent = [];
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $num = count($data);
            // echo "<p> $num Felder in Zeile $row: <br /></p>\n";
            //$row++;
            $line = [];
            for ($c = 0; $c < $num; $c++) {
                //echo $data[$c] . "<br />\n";
                $line[$firstline[$c]] = $data[$c];
            }
            $csvcontent[$line['id']] = $line;
        }
        fclose($handle);
        return $csvcontent;
    }

    /**
     * @return void
     */
    private function checkMails($number): void
    {
        $messages = $this->mailsink->get_messages();

        $this->assertEquals(1, count($messages));
        $msg = str_replace(["\r\n", "\r", "\n", "<br>", "</br>"], '', $messages[0]->body);

        $this->assertStringContainsString("In the last cron-job $number users were archived.", $msg);
        $this->assertStringContainsString('In the last cron-job 0 users were reactivated', $msg);
        $this->assertStringContainsString('No problems occurred in plugin tool_cleanupusers in the last run.', $msg);
    }

    /**
     * @return void
     */
    private function checkEvents(): void
    {
        $triggered = $this->eventsink->get_events();
        $this->eventsink->close();
        $found = false;
        foreach ($triggered as $event) {
            if ($event instanceof event\deprovisionusercronjob_completed) {
                $this->assertTrue(true, 'Completion event triggered.');
                $this->assertTrue($event->timecreated >= $this->timestamp, 'Completion event triggered correctly.');
                $found = true;
                break;
            }
        }
        if (!$found) {
            $this->fail('Completion event was not triggered.');
        }
    }

}
