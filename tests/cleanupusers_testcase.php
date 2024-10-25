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
 * The class contains a base class with tests helper functions
 *
 * @package    cleanupusers_testcase
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

require_once(__DIR__ . '/../classes/userstatuschecker.php');

/**
 * The class contains a base class with tests helper functions
 *
 * @package    cleanupusers_testcase
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class cleanupusers_testcase extends \advanced_testcase
{
    protected $generator = null;
    protected $checker = null;
    /**
     * @param array $returnsuspend
     * @param \stdClass|null $user
     * @return array
     */
    protected function assertEqualsUsersArrays(array $returnsuspend, ?\stdClass $user, $checker = null)
    {
        $this->assertEquals(1, count($returnsuspend));

        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returnsuspend), [$user->username]);

        // Compare content
        $archuser = reset($returnsuspend); // get one and only element from array
        $array2 = (array)($archuser);

        if (!isset($checker)) {
            $checker = get_class($this->checker);
            // strip namespace from checker class
            $index = strpos($checker, "\\");
            if ($index === false)
                throw new \coding_exception("cannot determine namespace of " . $checker);

            $checker = substr($checker, $index + 1);
            $user->checker = $checker; // checker is not contained in user => add for check
        } else {
            $user->checker = $checker;
        }

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

    protected function set_config($name, $value, $plugin = null) {
        set_config($name, $value, $plugin);
        // recreate checker in order to read new config
        $this->checker = $this->create_checker();
    }

    protected function get_archive() {
        global $DB;
        return $DB->get_records('tool_cleanupusers_archive', null, null, 'username');
    }

    protected function get_normal_users() {
        global $DB;
        return $DB->get_records('user', null, null, 'username');
    }

}