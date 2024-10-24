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
 * Sub-plugin lastloginchecker.
 *
 * @package   userstatus_lastloginchecker
 * @copyright 2016/17 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace userstatus_lastloginchecker;

use tool_cleanupusers\userstatuschecker;

/**
 * Class that checks the status of different users depending on the time they did not signed in.
 *
 * @package    userstatus_lastloginchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lastloginchecker extends userstatuschecker {
    /**
     * constructor
     */
    public function __construct() {
        parent::__construct(self::class);
    }

    public function condition_suspend_sql() : array {
        return [" lastaccess != 0 AND lastaccess < :timelimit" ,
            [ 'timelimit'  => time() - $this->get_suspendtime_in_sec() ]];
    }

    public function condition_reactivate_sql($tca, $tc) : array {
        return ["{$tca}.lastaccess >= :timelimit" ,
            [ 'timelimit'  => time() - $this->get_suspendtime_in_sec() ]];
    }


    /**
     * All users who never logged in will be returned in the array.
     * The array includes merely the necessary information which comprises the userid, lastaccess, suspended, deleted
     * and the username.
     *
     * @return array of users who never logged in
     */
    /*
    public function get_never_logged_in() {
        global $DB;
        $users = $DB->get_records_sql(
            "SELECT u.id, u.suspended, u.lastaccess, u.username, u.deleted
                FROM {user} u
                LEFT JOIN {tool_cleanupusers} tc ON u.id = tc.id
                WHERE " . $this->get_auth_sql('u.') . "
                    u.lastaccess = 0
                    AND u.deleted = 0
                    AND tc.id IS NULL"
        );
        $neverloggedin = [];
        foreach ($users as $key => $user) {
            $informationuser = new archiveduser(
                $user->id,
                $user->suspended,
                $user->lastaccess,
                $user->username,
                $user->deleted
            );
            $neverloggedin[$key] = $informationuser;
        }
        return $neverloggedin;
    }
*/
    /**
     * All users who should be deleted will be returned in the array.
     * The array includes merely the necessary information which comprises the userid, lastaccess, suspended, deleted
     * and the username.
     * The function checks the user table and the tool_cleanupusers_archive table. Therefore users who are suspended by
     * the tool_cleanupusers plugin and users who are suspended manually are screened.
     *
     * @return array of users who should be deleted.
     */
    /*
    public function get_to_delete() {
        global $DB;

        $users = $DB->get_records_sql(
            "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted
                FROM {user} u
                JOIN {tool_cleanupusers} tc ON u.id = tc.id
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                WHERE " . $this->get_auth_sql('u.') . "
                    u.suspended = 1
                    AND u.deleted = 0
                    AND tc.timestamp < :timelimit",
            [
                'timelimit'  => time() - $this->timedelete,
            ]
        );

        $todelete = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user)) {
                $deleteuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted
                );
                $todelete[$key] = $deleteuser;
            }
        }

        return $todelete;
    }*/

    /**
     * All user that should be reactivated will be returned.
     *
     * User should be reactivated when their lastaccess is smaller than the timesuspend variable. Although users are
     * not able to sign in when they are flagged as suspended, this is necessary to react when the timesuspended setting
     * is changed.
     *
     * @return array of objects
     */
    /*
    public function get_to_reactivate() {
        global $DB;

        $users = $DB->get_records_sql(
            "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted
                FROM {user} u
                JOIN {tool_cleanupusers} tc ON u.id = tc.id
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                WHERE " . $this->get_auth_sql('u.') . "
                    u.suspended = 1
                    AND u.deleted = 0
                    AND tca.lastaccess >= :timelimit
                    AND tca.username NOT IN
                        (SELECT username FROM {user} WHERE username IS NOT NULL)",
            [
                'timelimit'  => time() - $this->timesuspend,
            ]
        );

        $toactivate = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user)) {
                $activateuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted
                );
                $toactivate[$key] = $activateuser;
            }
        }

        return $toactivate;
    }
    */


}
