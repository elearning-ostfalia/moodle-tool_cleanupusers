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
 * External function for reactivating users.
 *
 * @package    tool_cleanupusers
 * @copyright  2025 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace tool_cleanupusers\external;

use external_function_parameters;
use external_multiple_structure;
// use external_single_structure;
use external_value;
use core_user;
use core_external;
use tool_cleanupusers\helper;

class reactivate_users extends \core_external\external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new \core_external\external_function_parameters([
            'useremails' => new \core_external\external_multiple_structure(
                    new \core_external\external_value(core_user::get_property_type('email'), 'user email')),
        ]);
    }

    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'useremails' => new \core_external\external_multiple_structure(
                    new \core_external\external_value(core_user::get_property_type('email'), 'user email')
            ),
            'warnings' => new \core_external\external_warnings()
        ]);


/*        return new \core_external\external_multiple_structure(
                new \core_external\external_value(core_user::get_property_type('email'), 'user email'),
        );*/
    }

    /**
     * Reactivate users
     * @param array $users array of user emails
     * @return array of emails of reactivated users
     */
    public static function execute($users) {
        global $CFG, $DB;
        require_once("$CFG->dirroot/group/lib.php");

        $params = self::validate_parameters(self::execute_parameters(),
                ['useremails' => $users]);

        // now security checks
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:update', $context);

        $reactivatedusers = [];
        $warnings = [];

        foreach ($params['useremails'] as $useremail) {
            // Catch any exception while updating a user and return it as a warning.
            try {
                $transaction = $DB->start_delegated_transaction();

                if (trim($useremail) == '') {
                    throw new \invalid_parameter_exception('Invalid email');
                }
                if ($DB->get_record('user', ['email' => $useremail])) {
                    throw new \invalid_parameter_exception("User with the email {$useremail} already exists");
                }

                // finally reactivate
                $record = $DB->get_record('tool_cleanupusers_archive', ['email' => $useremail],
                        'id, username, firstname, lastname, suspended, lastaccess, auth, deleted, timecreated');

                if ($record !== false) {
                    $result = helper::change_user_deprovisionstatus([$useremail => $record], 'reactivate', '');
                    if ($result['countersuccess'] == 1) {
                        $reactivatedusers[] = $useremail;
                    }
                }
                $transaction->allow_commit();
            } catch (\Exception $e) {
                try {
                    $transaction->rollback($e);
                } catch (\Exception $e) {
                    $warning = [];
                    $warning['item'] = 'user';
                    $warning['itemid'] = $useremail;
                    if ($e instanceof \moodle_exception) {
                        $warning['warningcode'] = $e->errorcode;
                    } else {
                        $warning['warningcode'] = $e->getCode();
                    }
                    $warning['message'] = $e->getMessage();
                    $warnings[] = $warning;
                }
            }
        }

        return ['warnings' => $warnings, 'useremails' => $reactivatedusers];
    }
}
