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
 * Data Generator for the userstatus_ldapchecker sub-plugin
 *
 * @package    userstatus_ldapchecker
 * @category   test
 * @copyright  2016/17 Nina Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Class Data Generator for the userstatus_ldapchecker sub-plugin
 *
 * @package    userstatus_ldapchecker
 * @category   test
 * @copyright  2016/17 Nina Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstatus_ldapchecker_generator extends testing_data_generator {
    /**
     * Creates users in Database to test the sub-plugin.
     */
    public function test_create_preparation () {
        global $DB;
        $generator = advanced_testcase::getDataGenerator();

        // Create users which are in the LDAP-response
        $generator->create_user(array('username' => 'tu_id_1', 'auth' => 'shibboleth'));
        $generator->create_user(array('username' => 'tu_id_2', 'auth' => 'shibboleth'));
        $generator->create_user(array('username' => 'tu_id_3', 'auth' => 'shibboleth'));
        $generator->create_user(array('username' => 'tu_id_4', 'auth' => 'shibboleth'));

        // Create user which should be suspended (not in lookup-table)
        $generator->create_user(array('username' => 'to_suspend', 'auth' => 'shibboleth'));

        // Create user which should be reactivated (are suspended but in lookup-table)
        $generator->create_user(array('username' => 'to_reactivate', 'auth' => 'shibboleth', 'suspended' => 1));

        // Create user which should NOT be reactivated (are suspended but NOT in lookup-table)
        $generator->create_user(array('username' => 'to_not_reactivate', 'auth' => 'shibboleth', 'suspended' => 1));

        // Create user which was suspended manually and should be deleted (not logged in since one year)
        $timestamponeyearago = time() - 366*86400;
        $generator->create_user(array('username' => 'to_delete_manually', 'auth' => 'shibboleth', 'suspended' => 1, 'lastaccess' => $timestamponeyearago));

        // Create user which was suspended with the plugin and should be deleted (was suspended one year ago or earlier)
        $delete = $generator->create_user(array('username' => 'anonym', 'firstname' => 'Anonym', 'auth'=>'shibboleth', 'suspended' => 1, 'lastaccess' => $timestamponeyearago));
        $DB->insert_record_raw('tool_cleanupusers', array('id' => $delete->id, 'archived' => true, 'timestamp' => $timestamponeyearago), true, false, true);
        $DB->insert_record_raw('tool_cleanupusers_archive', array('id' => $delete->id, 'auth'=>'shibboleth', 'username' => 'TO_DELETE_PLUGIN',
            'suspended' => 1, 'lastaccess' => $timestamponeyearago), true, false, true);
        $deleteduser = $delete;

        return $deleteduser;

    }
}