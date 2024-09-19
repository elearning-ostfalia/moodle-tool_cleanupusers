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

define('YEARAGO',       (time() - (86400 * 366)));
define('LAST_MONTH',    (time() - (86400 * 30)));
define('ELEVENDAYSAGO', (time() - (86400 * 11)));
define('NINEDAYSAGO',   (time() - (86400 * 9)));
define('YESTERDAY',     (time() - 86400));
define('TOMORROW',      (time() + 86400));

define('AUTH_METHOD', 'shibboleth');

abstract class userstatus_base_test extends \advanced_testcase
{
    protected $generator = null;
    protected $checker = null;
    /**
     * @param array $returnsuspend
     * @param \stdClass|null $user
     * @return array
     */
    protected function assertEqualsUsersArrays(array $returnsuspend, ?\stdClass $user)
    {
        $this->assertEquals(1, count($returnsuspend));

        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returnsuspend), [$user->username]);

        // Compare content
        $archuser = reset($returnsuspend); // get one and only element from array
        $array2 = (array)($archuser);
        $checker = get_class($this->checker);
        // strip namespace from checker class
        $index = strpos($checker, "\\");
        if ($index === false)
            throw new \coding_exception("cannot determin namespace of " . $checker);

        $checker = substr($checker, $index + 1);
        $user->checker = $checker; // checker is not contained in user => add for check
        $this->assertEquals($array2, array_intersect_assoc((array)$user, $array2));
    }

    protected function create_test_user($username, $extra_attributes = []) {
        return $this->generator->create_user(array_merge(
            ['username' => $username, 'auth' => AUTH_METHOD],
            $extra_attributes));
    }

    protected function create_user_and_enrol($username, $course = null) {
        $user = $this->create_test_user($username);
        if ($course != null) {
            $this->generator->enrol_user($user->id, $course->id);
        }
        return $user;
    }

    protected function archive($user, $when, $username) {
        $this->insert_into_metadata_table($user, $when);
        $this->insert_into_archive($user, $username);
    }

    protected function insert_into_archive($user, $username) {
        global $DB;
        $DB->insert_record_raw('tool_cleanupusers_archive', ['id' => $user->id, 'auth' => 'shibboleth',
            'username' => $username,
            'suspended' => $user->suspended, 'timecreated' => $user->timecreated],
            true, false, true);
    }

    protected function insert_into_metadata_table($user, $when) {
        global $DB;
        $DB->insert_record_raw('tool_cleanupusers',
            ['id' => $user->id, 'archived' => true,
                'timestamp' => $when, 'checker' => 'nocoursechecker'], true, false, true);
    }

    abstract public function typical_scenario_for_reactivation() : \stdClass;

    abstract public function typical_scenario_for_suspension() : \stdClass;

    abstract protected function create_checker();

    public function test_more_auth_suspend() {
        set_config('auth_method', 'email,' . AUTH_METHOD, 'userstatus_nocoursechecker');
        // Create new checker instance so that configuration will be "reread".
        $this->checker = $this->create_checker();
        $user = $this->typical_scenario_for_suspension('username');
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    // Common tests for all subplugins
    /**
     * like test_invisible_course_make_visisble_reactivate, but record in tool_cleanupusers is missing
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_incomplete_archive_no_reactivate_1() {
        $user = $this->typical_scenario_for_reactivation();
        global $DB;
        $DB->delete_records('tool_cleanupusers', ['id' => $user->id]); // NEW for test_invisible_course_make_visisble_reactivate
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

    public function test_incomplete_archive_no_reactivate_2() {
        $user = $this->typical_scenario_for_reactivation();
        global $DB;
        $DB->delete_records('tool_cleanupusers_archive', ['id' => $user->id]); // NEW for test_invisible_course_make_visisble_reactivate
        $this->assertEquals(0, count($this->checker->get_to_reactivate()));
    }

    /**
     * already manually suspended
     * @return void
     * @throws \dml_exception
     */
    public function test_already_suspended_not_suspend() {
        $user = $this->typical_scenario_for_suspension();
        global $DB;
        $user->suspended = 1;
        $DB->update_record('user', $user);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_already_deleted_not_suspend() {
        $user = $this->typical_scenario_for_suspension();
        global $DB;
        $user->deleted = 1;
        $DB->update_record('user', $user);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_no_delete() {
        $user = $this->typical_scenario_for_suspension();
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();
        $this->assertEquals(0, count($this->checker->get_to_delete()));
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
    }

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