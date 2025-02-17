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
            'type' => new \core_external\external_value(PARAM_ALPHA, 'username, email'),
            'user' => new \core_external\external_multiple_structure(
                    // checking of proper type would be better
                    new \core_external\external_value(PARAM_RAW, 'user identifier (username or email according to type)')),
        ]);
    }

    public static function execute_username_parameters() {
        return new \core_external\external_function_parameters([
                'type' => new \core_external\external_value(PARAM_ALPHA, 'username, email'),
                'user' => new \core_external\external_multiple_structure(
                        new \core_external\external_value(core_user::get_property_type('username'), 'username')),
        ]);
    }

    public static function execute_email_parameters() {
        return new \core_external\external_function_parameters([
                'type' => new \core_external\external_value(PARAM_ALPHA, 'username, email'),
                'user' => new \core_external\external_multiple_structure(
                        new \core_external\external_value(core_user::get_property_type('email'), 'email')),
        ]);
    }


    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'ids' => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'id' => new \core_external\external_value(PARAM_INT),
                    'username' => new \core_external\external_value(PARAM_TEXT),
                    'email' => new \core_external\external_value(PARAM_TEXT),
                ], 'user identifier'),
                'list of reactivated users'
            ),
            'warnings' => new \core_external\external_warnings()
        ]);
    }

    /**
     * Reactivate users
     * @param array $users array of user emails
     * @return array of emails of reactivated users
     */
    public static function execute(string $type, array $userids) {
        global $CFG, $DB;
        require_once("$CFG->dirroot/group/lib.php");

        $params = self::validate_parameters(self::execute_parameters(), [
                'type' => $type,
                'user' => $userids
        ]);

        if ($params['type'] == 'username') {
            $params = self::validate_parameters(self::execute_username_parameters(), [
                    'type' => $type,
                    'user' => $userids
            ]);
        } elseif ($params['type'] == 'email') {
            $params = self::validate_parameters(self::execute_email_parameters(), [
                    'type' => $type,
                    'user' => $userids
            ]);
        } else {
            throw new \invalid_parameter_exception('User identification type is invalid: ' . $params['type']);
        }

        if ($params['type'] != 'username' && $params['type'] != 'email') {
            // twice just in case...
            throw new \invalid_parameter_exception('User identification type is invalid: (2) ' . $params['type']);
        }

        // now security checks
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:update', $context);


        if (count($params['user']) == 0) {
            throw new \invalid_parameter_exception('User list must not be empty');
        }

        $reactivatedusers = [];
        $warnings = [];

        foreach ($params['user'] as $userid) {
            // Catch any exception while updating a user and return it as a warning.
            try {
                $transaction = $DB->start_delegated_transaction();

                if (trim($userid) == '') {
                    throw new \invalid_parameter_exception('User identification is empty');
                }
                if ($params['type'] == 'email') {
                    if ($DB->get_record('user', ['email' => $userid])) {
                        throw new \invalid_parameter_exception("User with the email {$userid} already exists");
                    }

                    // finally reactivate
                    $record = $DB->get_record('tool_cleanupusers_archive', ['email' => $userid],
                            'id, username, firstname, lastname, suspended, lastaccess, auth, deleted, timecreated, email');
                    if ($record === false) {
                        throw new \invalid_parameter_exception("User with the email {$userid} not found in archive");
                    }
                    $result = helper::change_user_deprovisionstatus([$userid => $record], 'reactivate', '');
                    if ($result['countersuccess'] == 1) {
                        $reactivateduser = [];
                        $reactivateduser['id'] = $record->id;
                        $reactivateduser['username'] = $record->username;
                        $reactivateduser['email'] = $userid;
                        $reactivatedusers[] = $reactivateduser;
                    }
                } else {
                    if ($DB->get_record('user', ['username' => $userid])) {
                        throw new \invalid_parameter_exception("User with the username {$userid} already exists");
                    }

                    // finally reactivate
                    $record = $DB->get_record('tool_cleanupusers_archive', ['username' => $userid],
                            'id, username, firstname, lastname, suspended, lastaccess, auth, deleted, timecreated, email');
                    if ($record === false) {
                        throw new \invalid_parameter_exception("User with the username {$userid} not found in archive");
                    }
                    $result = helper::change_user_deprovisionstatus([$userid => $record], 'reactivate', '');
                    if ($result['countersuccess'] == 1) {
                        $reactivateduser = [];
                        $reactivateduser['id'] = $record->id;
                        $reactivateduser['username'] = $userid;
                        $reactivateduser['email'] = $record->email;
                        $reactivatedusers[] = $reactivateduser;
                    }
                }
                $transaction->allow_commit();
            } catch (\Exception $e) {
                try {
                    $transaction->rollback($e);
                } catch (\Exception $e) {
                    $warning = [];
                    $warning['item'] = $userid;
                    // $warning['itemid'] = $username;
                    if ($e instanceof \moodle_exception) {
                        $warning['warningcode'] = $e->errorcode;
                        // debug info is required in order to simplify debugging
                        if (str_contains($e->getMessage(), $e->debuginfo)) {
                            $warning['message'] = $e->getMessage();
                        } else {
                            $warning['message'] = $e->getMessage() . ': ' . $e->debuginfo;
                        }
                    } else {
                        $warning['warningcode'] = $e->getCode();
                        $warning['message'] = $e->getMessage();
                    }
                    $warnings[] = $warning;
                }
            }
        }

        return ['ids' => $reactivatedusers, 'warnings' => $warnings];
    }

}
