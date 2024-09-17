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
 * Data Generator for the userstatus_neverloginchecker sub-plugin
 *
 * @package    userstatus_timechecker
 * @category   test
 * @copyright  2016/17 Nina Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class Data Generator for the userstatus_timechecker sub-plugin
 *
 * @package    userstatus_timechecker
 * @category   test
 * @copyright  2016/17 Nina Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstatus_neverloginchecker_generator extends testing_data_generator {

    private function create_test_user($username, $extra_attributes = []) {
        $generator = advanced_testcase::getDataGenerator();
        return $generator->create_user(array_merge(['username' => $username, 'auth' => 'shibboleth'],
            $extra_attributes));
    }

    private function archive($user, $when, $username) {
        $this->insert_into_metadata_table($user, $when);
        $this->insert_into_archive($user, $username);

    }

    private function insert_into_metadata_table($user, $when) {
        global $DB;
        $DB->insert_record_raw('tool_cleanupusers',
            ['id' => $user->id, 'archived' => true,
                'timestamp' => $when, 'checker' => 'neverloginchecker'], true, false, true);
    }

    private function insert_into_archive($user, $username) {
        global $DB;
        $DB->insert_record_raw('tool_cleanupusers_archive', ['id' => $user->id, 'auth' => 'shibboleth',
            'username' => $username,
            'suspended' => $user->suspended, 'timecreated' => $user->timecreated],
            true, false, true);
    }

    /**
     * Creates users to test the sub-plugin.
     */
    public function test_create_preparation() {
        global $DB;

        $yearago = time() - 366 * 86400;
        $dayago = time() - 86400;
        $elevendaysago = time() - (86400 * 11);

        // Create users which are not yet handled by this plugin.
        $this->create_test_user('tu_id_1', ['timecreated' => $dayago]);
        $this->create_test_user('tu_id_2', ['timecreated' => $dayago]);
        $this->create_test_user('tu_id_3', ['timecreated' => $dayago]);
        $this->create_test_user('tu_id_4', ['timecreated' => $dayago]);

        // Create user which should be suspended (created 11 days ago).
        $this->create_test_user('to_suspend', ['timecreated' => $elevendaysago]);

        // Create users which should NOT be suspended as they are suspended by admin user.
        $this->create_test_user('manually_suspended', ['suspended' => 1]);
        $this->create_test_user('manually_deleted', ['deleted' => 1]);

        // Create user which should be reactivated (current time - timecreated < time to suspend).
        $reactivate = $this->create_test_user('anonym1', ['firstname' => 'Anonym',
            'suspended' => 1, 'timecreated' => $dayago]);
        $this->archive($reactivate, $dayago, 'to_reactivate');

        // Create users which should NOT be reactivated.
        $notreactivate1 = $this->create_test_user('to_not_reactivate',
            ['suspended' => 1, 'timecreated' => $yearago]);

        $this->create_test_user('to_not_reactivate_username_taken',
            ['suspended' => 1, 'timecreated' => $dayago]);

        $notreactivate2 = $this->create_test_user('anonym2',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => $dayago]);
        $this->archive($notreactivate2, $dayago, 'to_not_reactivate_username_taken');


        // Create user which was suspended with the plugin and should be deleted (time - suspended
        // >= time to delete).
        $delete = $this->create_test_user('anonym6',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => $yearago]);
        $this->archive($delete, $yearago, 'to_delete');

        // Create users which were suspended with the plugin and should NOT be deleted.
        $notdelete1 = $this->create_test_user('anonym7',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => $elevendaysago]);
        $this->archive($notdelete1, $dayago, 'to_not_delete_one_day');

        // Incomplete archive data (will not be suspended or reactivated as
        // they're not selected by SQL Join)
        // --------------
        $notreactivate3 = $this->create_test_user('anonym3',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => $dayago]);
        $DB->insert_record_raw('tool_cleanupusers', ['id' => $notreactivate3->id, 'archived' => true,
            'timestamp' => $dayago, 'checker' => 'neverloginchecker'], true, false, true);

        $notreactivate4 = $this->create_test_user('anonym4',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => $dayago]);
        $DB->insert_record_raw('tool_cleanupusers_archive', ['id' => $notreactivate4->id, 'auth' => 'shibboleth',
            'username' => 'to_not_reactivate_entry_missing',
            'suspended' => 1, 'timecreated' => $dayago], true, false, true);

        $notreactivate5 = $this->create_test_user('anonym5',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => $dayago]);

        // Incomplete delete data
        $notdelete2 = $this->create_test_user('anonym8',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => $elevendaysago]);
        $DB->insert_record_raw(
            'tool_cleanupusers',
            ['id' => $notdelete2->id, 'archived' => true, 'timestamp' => $dayago, 'checker' => 'neverloginchecker'],
            true,
            false,
            true
        );

        $notdelete3 = $this->create_test_user('anonym9',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => 0]);
        $DB->insert_record_raw('tool_cleanupusers_archive', ['id' => $notdelete3->id, 'auth' => 'shibboleth',
            'username' => 'to_not_delete_entry_missing', 'suspended' => 1, 'lastaccess' => $yearago], true, false, true);

        $notdelete4 = $this->create_test_user('anonym10',
            ['firstname' => 'Anonym', 'suspended' => 1, 'timecreated' => 0]);
    }
}
