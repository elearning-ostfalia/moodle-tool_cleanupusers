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
 * The class contains a test script for the moodle userstatus_lastloginchecker
 *
 * @package    userstatus_lastloginchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace userstatus_lastloginchecker;

require_once(__DIR__.'/../../../tests/userstatus_base_test.php');

use advanced_testcase;

/**
 * The class contains a test script for the moodle userstatus_lastloginchecker
 *
 * @package    userstatus_lastloginchecker
 * @group      tool_cleanupusers
 * @group      tool_cleanupusers_timechecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class userstatus_lastloginchecker_test extends \tool_cleanupusers\userstatus_base_test {

    protected function setup() : void {
        $this->generator = advanced_testcase::getDataGenerator();
        $this->resetAfterTest(true);

        // set enabled plugin for running task
        set_config(CONFIG_ENABLED, "lastloginchecker");
        set_config(CONFIG_AUTH_METHOD, AUTH_METHOD, 'userstatus_lastloginchecker');
        set_config(CONFIG_SUSPENDTIME, 10, 'userstatus_lastloginchecker');
        set_config(CONFIG_DELETETIME, 365, 'userstatus_lastloginchecker');

        $this->checker = new \userstatus_lastloginchecker\lastloginchecker();
    }

    /**
     * Create the data from the generator.
     * @return mixed
     */
    /*
    protected function set_up() {
        // Recommended in Moodle docs to always include CFG.
        global $CFG;
        $generator = $this->getDataGenerator()->get_plugin_generator('userstatus_lastloginchecker');
        $data = $generator->test_create_preparation();
        $this->resetAfterTest(true);
        set_config('userstatus_plugins_enabled', "neverloginchecker,lastloginchecker");
        // set configuration values for lastloginchecker
        set_config('auth_method', 'shibboleth', 'userstatus_lastloginchecker');
        set_config('suspendtime', 10, 'userstatus_lastloginchecker');
        set_config(CONFIG_DELETETIME, 365, 'userstatus_lastloginchecker');
        return $data;
    }*/

    protected function create_checker() {
        return new \userstatus_lastloginchecker\lastloginchecker();
    }

    public function typical_scenario_for_reactivation() : ?\stdClass {
        $user = $this->create_test_user('username', ['lastaccess' => ELEVENDAYSAGO]);
        $this->assertEquals(0, $user->suspended);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);

        // run cron
        $cronjob = new \tool_cleanupusers\task\archive_user_task();
        $cronjob->execute();

        // change suspend time to 12 days
        set_config(CONFIG_SUSPENDTIME, 12, 'userstatus_lastloginchecker');
        // create new checker instance in order to read changes values
        $this->checker = new \userstatus_lastloginchecker\lastloginchecker();
        return $user;
    }

    public function typical_scenario_for_suspension() : \stdClass {
        return $this->create_test_user('username', ['lastaccess' => ELEVENDAYSAGO]);
    }

    // TESTS
    // ---------------------------------------------
    // Suspend: scenarios not handled by this plugin
    // ---------------------------------------------
    public function test_yesterday_no_suspend() {
        $this->create_test_user('username', ['lastaccess' => YESTERDAY]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    public function test_9_days_ago_no_suspend() {
        $this->create_test_user('username', ['lastaccess' => NINEDAYSAGO]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));
    }

    // ---------------------------------------------
    // Suspend: scenarios handled by this plugin
    // ---------------------------------------------
    public function test_11_days_ago_suspend() {
        $user = $this->create_test_user('username', ['lastaccess' => ELEVENDAYSAGO]);
        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }

    public function test_9_days_ago_change_suspend_time_suspend() {
        $user = $this->create_test_user('username', ['lastaccess' => NINEDAYSAGO]);
        $this->assertEquals(0, count($this->checker->get_to_suspend()));

        // change suspend time to 8 days
        set_config(CONFIG_SUSPENDTIME, 8, 'userstatus_lastloginchecker');
        $this->checker = new \userstatus_lastloginchecker\lastloginchecker();

        $this->assertEqualsUsersArrays($this->checker->get_to_suspend(), $user);
    }
}
