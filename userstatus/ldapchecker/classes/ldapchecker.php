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
 * Sub-plugin ldapchecker.
 *
 * @package   userstatus_ldapchecker
 * @copyright 2016/17 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace userstatus_ldapchecker;

use core\session\exception;
use tool_cleanupusers\archiveduser;
use tool_cleanupusers\userstatusinterface;
use tool_cleanupusers\userstatuschecker;




defined('MOODLE_INTERNAL') || die;

/**
 * Class that checks the status of different users depending on the time they have not signed in for.
 *
 * @package    userstatus_ldapchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class ldapchecker extends userstatuschecker { // implements userstatusinterface {

    /** @var array lookuptable for ldap users */
    protected $lookup = array();

    private $testing = false;

    /**
     * This constructor sets timesuspend and timedelete from days to seconds.
     * @throws \dml_exception
     */
    public function __construct($testing = false) {
        parent::__construct(get_class(), $testing);

        // Calculates days to seconds.
        $this->testing = $testing;
    }

    private function init() {
        // Only connect to LDAP if we are not in testing case
        if ($this->testing === false && !defined('PHPUNIT_COMPOSER_INSTALL')) {

            $ldap = ldap_connect($this->config->host_url) or die("Could not connect to $this->config->host_url");
            try {
                $bind = ldap_bind($ldap, $this->config->bind_dn, $this->config->bind_pw); // returns 1 if correct

                if($bind) {
                    $this->log("ldap_bind successful");

                    $contexts = $this->config->contexts;

                    $uid = $this->config->ldap_username_attribute;
                    $attributes = [$uid];
                    $filter = $this->config->search_filter; // '(cn=*)';
                    $search = ldap_search($ldap, $contexts, $filter, $attributes) or die("Error in search Query: " . ldap_error($ldap));
                    $result = ldap_get_entries($ldap, $search);

                    foreach ($result as $user) {
                        if(isset($user[$uid])) {
                            foreach ($user[$uid] as $cn) {
                                $this->lookup[$cn] = true;
                            }
                        }
                    }

                    $this->log("ldap server sent " . count($this->lookup) . " users");
                    global $SESSION;
                    $SESSION->cleanupusers_LDAP_cache = $this->lookup;
                    // cache is valid for 10 minutes
                    $SESSION->cleanupusers_LDAP_cache_ttl = time() + (60 * 10);

                } else {
                    $this->log("ldap_bind failed");
                    // fatal error!
                    throw new exception("cannot connect to LDAP server");
                }
            } finally {
                // Close LDAP connection.
                ldap_close($ldap);
            }
        }
    }

    public function invalidate_cache() : void {
        global $SESSION;
        unset($SESSION->cleanupusers_LDAP_cache);
        unset($SESSION->cleanupusers_LDAP_cache_ttl);
    }

    private function is_initialised() : bool {
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            return true;
        }
        if (count($this->lookup) == 0) {
            global $SESSION;
            if (isset($SESSION->cleanupusers_LDAP_cache) && count($SESSION->cleanupusers_LDAP_cache) > 0) {
                if (isset($SESSION->cleanupusers_LDAP_cache_ttl) && $SESSION->cleanupusers_LDAP_cache_ttl > time()) {
                    debugging('use ldap cache');
                    $this->lookup = $SESSION->cleanupusers_LDAP_cache;
                    return true;
                }
            }

            $this->init();
            if (count($this->lookup) == 0) {
                return false;
            }
        }
        return true;
    }


    /**
     * part of SQL where clause to perform the check for suspension:
     * no user data from Moodle data is considered, not even the suspended flag.
     * => if a user authenticated by LDAP is suspended manually,
     * then he will always be archived if he is removed from LDAP.
     * If this is not what is desired then the suspended checker should be
     * moved in front of the ldap checker
     *
     * @return array
     */
    public function condition_suspend_sql() : array {
        return ["" , null];
    }

    public function shall_suspend($user) : bool {
        // check initialisation state (todo: should not be checked for every user!)
        if (!$this->is_initialised()) {
            throw new \moodle_exception('No users from LDAP available');
        }
        // var_dump($this->lookup);
        return (!array_key_exists($user->username, $this->lookup));
    }

    public function shall_reactivate($user) : bool {
        return !$this->shall_suspend($user);
    }


    /** does not use suspend time value */
    public function needs_suspendtime() : bool {
        return false;
    }

    /**
     * All users who are not suspended and not deleted are selected. If a user did not sign in for the hitherto
     * determined suspendtime he/she will be returned.
     * Users not signed in for the hitherto determined suspendtime, do not show up in the ldap lookuptable.
     * The array includes merely the necessary information which comprises the userid, lastaccess, suspended, deleted
     * and the username.
     *
     * @return array of users to suspend
     * @throws \dml_exception
     */
    /*
    public function get_to_suspend() {
        global $DB;
        if (count($this->lookup) == 0) {
            $this->init();
            if (count($this->lookup) == 0) {
                $this->log("no users from LDAP found => do not evaluate users to suspend");
                return [];
            }
        }

        $select = "auth='" . $this->config->auth_method . "' AND deleted=0 AND suspended=0";
        $this->log("[get_to_suspend] " . $select);
        $users = $DB->get_records_select('user', $select, null, '',
            "id, suspended, lastaccess, username, deleted");
        $this->log("[get_to_suspend] found " . count($users) . " users in user table to check");

        $tosuspend = [];

        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && !array_key_exists($user->username, $this->lookup)) {
                $suspenduser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted);
                $tosuspend[$key] = $suspenduser;
                $this->log("[get_to_suspend] " . $user->username . " marked");
            }
        }
        $this->log("[get_to_suspend] marked " . count($tosuspend) . " users");

        return $tosuspend;
    }*/

    /**
     * All users who never logged in will be returned in the array.
     * The array includes merely the necessary information which comprises the userid, lastaccess, suspended, deleted
     * and the username.
     *
     * @return array of users who never logged in
     * @throws \dml_exception
     */
    /*
    public function get_never_logged_in() {
        global $DB;
        $arrayofuser = $DB->get_records_sql(
            "SELECT u.id, u.suspended, u.lastaccess, u.username, u.deleted
                FROM {user} u
                LEFT JOIN {tool_cleanupusers} tc ON u.id = tc.id
                WHERE u.auth = '" . $this->config->auth_method . "'
                    AND u.lastaccess = 0
                    AND u.deleted = 0
                    AND tc.id IS NULL"
        );

        $neverloggedin = [];
        foreach ($arrayofuser as $key => $user) {
            $informationuser = new archiveduser(
                $user->id,
                $user->suspended,
                $user->lastaccess,
                $user->username,
                $user->deleted);
            $neverloggedin[$key] = $informationuser;
        }

        return $neverloggedin;
    }
*/
    /**
     * All users who should be deleted will be returned in the array.
     * The array includes merely the necessary information which comprises the userid, lastaccess, suspended, deleted
     * and the username.
     * The function checks the user table and the tool_cleanupusers_archive table. Therefore, users who are suspended by
     * the tool_cleanupusers plugin and users who are suspended manually are screened.
     *
     * @return array of users who should be deleted.
     * @throws \dml_exception
     */
    /*
    public function get_to_delete() {
        global $DB;

        if (count($this->lookup) == 0) {
            $this->init();
            if (count($this->lookup) == 0) {
                $this->log("no users from LDAP found => do not evaluate users to delete");
                return [];
            }
        }

        $users = $DB->get_records_sql(
            "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted
                FROM {user} u
                JOIN {tool_cleanupusers} tc ON u.id = tc.id
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                WHERE u.auth = '" . $this->config->auth_method . "'
                    AND u.suspended = 1
                    AND u.deleted = 0
                    AND tc.timestamp < :timelimit",
            [
                'timelimit'  => time() - $this->timedelete,
            ]
        );
        $todelete = [];

        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && !array_key_exists($user->username, $this->lookup)) {
                $deleteuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted
                );
                $todelete[$key] = $deleteuser;
                $this->log("[get_to_delete] " . $user->username . " marked");
            }
        }
        $this->log("[get_to_delete] marked " . count($todelete) . " users");

        return $todelete;
    }*/

    /**
     * All users that should be reactivated will be returned.
     *
     * @return array of objects
     * @throws \dml_exception
     * @throws \dml_exception
     */
    /*
    public function get_to_reactivate() {
        global $DB;
        if (count($this->lookup) == 0) {
            $this->init();
            if (count($this->lookup) == 0) {
                $this->log("no users from LDAP found => do not evaluate users to reactivate");
                return [];
            }
      }

        // Only users who are currently suspended are relevant.
        $users = $DB->get_records_sql(
            "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted
                FROM {user} u
                JOIN {tool_cleanupusers} tc ON u.id = tc.id
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                WHERE u.auth = '" . $this->config->auth_method . "'
                    AND u.suspended = 1
                    AND u.deleted = 0
                    AND tca.username NOT IN
                        (SELECT username FROM {user} WHERE username IS NOT NULL)"
        );
        $toactivate = [];

        // $config = get_config('userstatus_ldapchecker');

        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && array_key_exists($user->username, $this->lookup)) {
                $activateuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted
                );
                $toactivate[$key] = $activateuser;
                $this->log("[get_to_reactivate] " . $user->username . " marked");
            }
        }
        $this->log("[get_to_reactivate] marked " . count($toactivate) . " users");

        return $toactivate;
    }
    */

    public function fill_ldap_response_for_testing($dummy_ldap) {
        if ($this->testing || defined('PHPUNIT_COMPOSER_INSTALL')) {
            // only for testing
            $this->lookup = $dummy_ldap;
        }
    }

}
