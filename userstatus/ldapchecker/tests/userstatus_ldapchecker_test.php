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

defined('MOODLE_INTERNAL') || die();

/**
 * The class contains a test script for the moodle userstatus_ldapchecker
 *
 * @package    userstatus_ldapchecker
 * @category   phpunit
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstatus_ldapchecker_test extends advanced_testcase {

    protected function set_up() {
        $config = get_config('userstatus_ldapchecker');
        $config->deletetime = 356; // Set time for deleting users in days

        $generator = $this->getDataGenerator()->get_plugin_generator('userstatus_ldapchecker');
        $data = $generator->test_create_preparation();
        $this->resetAfterTest(true);
        return $data;
    }
    /**
     * Function to test the class ldapchecker.
     *
     * @see ldapchecker
     */
    public function test_locallib() {
        $deleteduser_by_plugin = $this->set_up();

        // Testing is set to true which means that it does not try to connect to LDAP.
        $myuserstatuschecker = new ldapchecker(true);

        $myuserstatuschecker->fill_ldap_response_for_testing(array( "tu_id_1" => 1,
                                                                    "tu_id_2" => 1,
                                                                    "tu_id_3" => 1,
                                                                    "tu_id_4" => 1,
                                                                ));

        // User to suspend
        $returnsuspend = $myuserstatuschecker->get_to_suspend();
        $this->assertEquals("to_suspend", reset($returnsuspend)->username);

        // Add user which should be reactivated
        $myuserstatuschecker->fill_ldap_response_for_testing(array( "tu_id_1" => 1,
            "tu_id_2" => 1,
            "tu_id_3" => 1,
            "tu_id_4" => 1,
            "to_reactivate" => 1,
        ));
        $returntoreactivate = $myuserstatuschecker->get_to_reactivate();
        $this->assertEquals("to_reactivate", reset($returntoreactivate)->username);
        $this->assertEquals("to_reactivate", end($returntoreactivate)->username);

        $returndelete = $myuserstatuschecker->get_to_delete();
        $this->assertEquals("to_delete_manually", reset($returndelete)->username);
        $this->assertEquals($deleteduser_by_plugin->id, end($returndelete)->id);

        $this->resetAfterTest(true);

    }
    /**
     * Methods recommended by moodle to assure database and dataroot is reset.
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
     * Methods recommended by moodle to assure database is reset.
     */
    public function test_user_table_was_reset() {
        global $DB;
        $this->assertEquals(2, $DB->count_records('user', array()));
        $this->assertEquals(0, $DB->count_records('tool_cleanupusers', array()));
    }
}