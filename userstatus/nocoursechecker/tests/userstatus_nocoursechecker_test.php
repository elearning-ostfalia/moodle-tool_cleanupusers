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

namespace userstatus_nocoursechecker;
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
 * @covers \userstatus_timechecker\timechecker::get_to_delete()
 * @covers \userstatus_timechecker\timechecker::get_to_reactivate()
 *
 */

define('YESTERDAY', (time() - 86400));
define('TOMORROW', (time() + 86400));
define('LAST_MONTH', (time() - (86400 * 30)));

class userstatus_nocoursechecker_test extends advanced_testcase {
    protected $generator = null;
    protected $checker = null;

    private function create_test_user($username, $extra_attributes = []) {
        $generator = advanced_testcase::getDataGenerator();
        return $generator->create_user(array_merge(['username' => $username, 'auth' => 'shibboleth'],
            $extra_attributes));
    }

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
        $user->checker = "nocoursechecker"; // checker is not contained in user
        $this->assertEquals($array2, array_intersect_assoc((array)$user, $array2));
    }

    private function archive($user, $when, $username) {
        $this->insert_into_metadata_table($user, $when);
        $this->insert_into_archive($user, $username);
    }

    private function insert_into_archive($user, $username) {
        global $DB;
        $DB->insert_record_raw('tool_cleanupusers_archive', ['id' => $user->id, 'auth' => 'shibboleth',
            'username' => $username,
            'suspended' => $user->suspended, 'timecreated' => $user->timecreated],
            true, false, true);
    }

    private function insert_into_metadata_table($user, $when) {
        global $DB;
        $DB->insert_record_raw('tool_cleanupusers',
            ['id' => $user->id, 'archived' => true,
                'timestamp' => $when, 'checker' => 'nocoursechecker'], true, false, true);
    }

    /**
     * Create the data from the generator.
     * @return mixed
     */
    protected function set_up() {
        // Recommended in Moodle docs to always include CFG.
        global $CFG;
        $generator = $this->getDataGenerator()->get_plugin_generator('userstatus_nocoursechecker');
        $data = $generator->test_create_preparation();
        set_config('auth_method', 'shibboleth', 'userstatus_nocoursechecker');
        set_config('deletetime', 10, 'userstatus_nocoursechecker');

        $this->resetAfterTest(true);
        return $data;
    }

    protected function init($username = '', $course = null) {
        if (!$this->generator)
            $this->generator = advanced_testcase::getDataGenerator();
        $this->checker = new nocoursechecker();
        $this->resetAfterTest(true);
        if (!empty($username)) {
            $user = $this->create_test_user($username);
            if ($course != null) {
                $this->generator->enrol_user($user->id, $course->id);
            }
            return $user;
        }
        return null;
    }

    public function test_active_course_no_suspend() {
        $this->generator = advanced_testcase::getDataGenerator();
        $active_course = $this->generator->create_course(['startdate' => YESTERDAY, 'enddate' => TOMORROW, 'visible' => true]);
        $this->init('username', $active_course);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_future_course_no_suspend() {
        $this->generator = advanced_testcase::getDataGenerator();
        $future_course = $this->generator->create_course(['startdate' => TOMORROW, 'visible' => true]);
        $this->init('username', $future_course);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_open_course_no_suspend() {
        $this->generator = advanced_testcase::getDataGenerator();
        $active_endless_course = $this->generator->create_course(['startdate' => YESTERDAY, 'visible' => true]);
        $this->init('username', $active_endless_course);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_no_course_suspend() {
        $user = $this->init('username');
        $returnsuspend = $this->checker->get_to_suspend();
        $this->assertEqualsUsersArrays($returnsuspend, $user);
    }

    public function test_invisible_course_suspend() {
        $this->generator = advanced_testcase::getDataGenerator();
        $invisible_course = $this->generator->create_course(['startdate' => YESTERDAY, 'visible' => false]);
        $user = $this->init('username', $invisible_course);

        $checker = new nocoursechecker();
        $returnsuspend = $checker->get_to_suspend();

        $this->assertEqualsUsersArrays($returnsuspend, $user);
    }


    public function test_locallib() {
        $data = $this->set_up();
        $checker = new nocoursechecker();

        // To suspend.
        $suspend = ["to_suspend_1", "to_suspend_2", "to_suspend_3"];
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
        set_config('deletetime', 0.5, 'userstatus_neverloginchecker');
        $newchecker = new nocoursechecker();

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
