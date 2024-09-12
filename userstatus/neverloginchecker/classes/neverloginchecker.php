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
 * Sub-plugin timechecker.
 *
 * @package   userstatus_timechecker
 * @copyright 2016/17 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace userstatus_neverloginchecker;

use tool_cleanupusers\archiveduser;
use tool_cleanupusers\userstatusinterface;
use tool_cleanupusers\userstatuschecker;

/**
 * Class that checks the status of different users depending on the time they did not signed in.
 *
 * @package    userstatus_neverloginchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class neverloginchecker extends userstatuschecker {
    /**
     * This constructor sets timesuspend and timedelete from days to seconds.
     */
    public function __construct() {
        parent::__construct(self::class);
    }

    public function condition_suspend_sql() : array {
        return [" lastaccess = 0 and timecreated < :timelimit" ,
            [ 'timelimit'  => time() - $this->get_suspendtime_in_sec() ]];
    }

    /**
     * need to be handled because the timelimit might have changed in meantime
     * @param $tca
     * @param $tc
     * @return array
     */
    public function condition_reactivate_sql($tca, $tc) : array {
        return ["({$tca}.lastaccess != 0 or {$tca}.timecreated >= :timelimit)" ,
            [ 'timelimit'  => time() - $this->get_suspendtime_in_sec() ]];
    }

/*    private function get_auth_sql($alias) : string {
        if (empty($this->config->auth_method))
            return '';
        return $alias . "auth = '" . $this->config->auth_method . "' AND ";
    }*/

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
     * returns the authentication method for all users being handled by this plugin
     * @return string
     */
    /*
    public function get_authentication_method() :string {
        return $this->config->auth_method;
    }*/
}
