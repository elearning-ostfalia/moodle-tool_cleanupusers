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
 * The class contains a test script for the moodle userstatus_suspendedchecker
 *
 * @package    userstatus_suspendedchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace userstatus_suspendedchecker;

require_once(__DIR__.'/../../../tests/userstatus_base_test.php');

use advanced_testcase;

/**
 * The class contains a test script for the moodle userstatus_suspendedchecker
 *
 * @package    userstatus_suspendedchecker
 * @group      tool_cleanupusers
 * @group      tool_cleanupusers_suspendedchecker
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
#[CoversClass(\userstatus_suspendedchecker\suspendedchecker::class)]
final class userstatus_suspendedchecker_test extends \tool_cleanupusers\userstatus_base_test {

    protected function setup(): void {
        $this->generator = advanced_testcase::getDataGenerator();
        $this->resetAfterTest(true);

        // set enabled plugin for running task
        set_config(CONFIG_ENABLED, "suspendedchecker");
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD, 'userstatus_suspendedchecker');
        set_config(CONFIG_DELETETIME, 365, 'userstatus_suspendedchecker');
        set_config(CONFIG_SUSPENDTIME, 10, 'userstatus_suspendedchecker');

        $this->checker = new suspendedchecker();
    }

    protected function create_checker() {
        return new suspendedchecker();
    }

    public function typical_scenario_for_suspension(): \stdClass {
        $user = $this->create_test_user('username',  ['suspended' => 1]);
        // timemodified cannot be set on creation as it is overriden!
        global $DB;
        $user->timemodified = ELEVENDAYSAGO;
        $DB->update_record('user', $user);
        return $user;
    }

    /**
     * do not suspend if timelimit is not yet reached.
     * @return void
     * @throws \dml_exception
     */
    public function test_already_deleted_not_suspend() {
        $user = $this->create_test_user('username',  ['suspended' => 1]);
        // timemodified cannot be set on creation as it is overriden!
        global $DB;
        $user->timemodified = NINEDAYSAGO;
        $DB->update_record('user', $user);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }




        /**
     * User cannot be reactivated by external circumstances. He or she must be reactivated
     * manually
     *
     * @return \stdClass
     * @throws \coding_exception
     */
    public function typical_scenario_for_reactivation(): ?\stdClass {
        return null;
    }
}
