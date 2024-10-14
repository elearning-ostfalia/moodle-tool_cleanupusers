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
 * Base class for checker classes
 *
 * @package   tool_cleanupusers
 * @copyright 2024 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers;

/**
 * base class for all subplugin classes
 */
abstract  class userstatuschecker
{
    protected $baseconfig;

    protected $config;

    protected $name;

    public function __construct($name, $testing = false) {

        $this->baseconfig = get_config('tool_cleanupusers');

        $class_parts = explode('\\', $name);
        $name = end($class_parts);

        $this->name = $name;
        $this->config = get_config('userstatus_' . $name);
    }


    /**
     * returns the condition for the subplugin
     *
     * @return string
     */
    public function get_condition_text() : string {
        return get_string('condition', 'userstatus_' . $this->name);
    }

    public function get_suspend_hint() : string {
        return get_string('suspendtime', 'userstatus_' . $this->name);
    }

    public function get_name() : string {
        return $this->name;
    }

    public function get_displayname() : string {
        return get_string('pluginname', 'userstatus_' . $this->name);
    }

    /**
     * check if the given user fulfills the suspension condition
     *
     * @param $user
     * @return true
     */
    public function shall_suspend($user) : bool {
        return true;
    }

    /**
     * check if the given user fulfills the reactivation condition
     *
     * @param $user
     * @return true
     */
    public function shall_reactivate($user) : bool {
        return true;
    }

    public function condition_suspend_sql() : array {
        return ["suspended = 0" , null];
    }

    public function condition_reactivate_sql($tca, $tc) {
        return ['', null];
    }

    /**
     * returns the authentication method for all users being handled by this plugin
     * @return string
     */
    public function get_authentication_method() :string {
        if (!isset($this->config) || !isset($this->config->auth_method))
            return '';
        return $this->config->auth_method;
    }

    /**
     * whether or not the suspendtime field is needed
     * @return bool
     */
    public function needs_suspendtime() : bool {
        return true;
    }
    /**
     * returns the period after suspension before deletion
     * @return string
     */
    public function get_deletetime() : float {
        if (!isset($this->config->deletetime) || $this->config->deletetime == null) {
            // initial state
            return 365;
        }
        return $this->config->deletetime;
    }

    public function get_suspendtime() : float {
        if (!isset($this->config->suspendtime) || $this->config->suspendtime == null) {
            // initial state
            return 365;
        }
        return $this->config->suspendtime;
    }

    public function get_deletetime_in_sec() : int {
        return $this->get_deletetime() * 86400;
    }

    public function get_suspendtime_in_sec() : int {
        return $this->get_suspendtime() * 86400;
    }

    /**
     * what shall be done if a user is about to be suspended (archived) but
     * he or she has never logged in
     * @return bool
     */
    public function delete_if_never_logged_in_on_suspendtime() : bool {
        if (!isset($this->config->deleteifneverloggedin) || $this->config->deleteifneverloggedin == null) {
            return false;
        }
        return $this->config->deleteifneverloggedin;
    }

    protected function log($text) {
        /*
        file_put_contents($this->baseconfig->log_folder . "/debug_log_ldapchecker.log",
            "\n[".date("d-M-Y - H:i ")."] $text " , FILE_APPEND);
        */
    }

    private function get_auth_sql($alias) : string {
        if (empty($this->get_authentication_method()))
            return ' true ';
        // In case there are more authentication methods selected
        // the sql string will be e.g.
        // "(u.auth = 'email' or u.auth = 'ldap')"
        $methods = explode(',', $this->get_authentication_method());
        $methods = implode("' or {$alias}auth = '", $methods);

        return  "({$alias}auth = '" . $methods . "')  ";
    }

    public function get_to_suspend() {
        global $DB;

        list($sql_condition, $param_condition) = $this->condition_suspend_sql();
        $sql = "SELECT id, suspended, lastaccess, username, deleted, auth
                FROM {user}
                WHERE " . $this->get_auth_sql('') . "
                    AND deleted = 0";
        if (!empty($sql_condition)) {
            $sql .= " AND " . $sql_condition;
        }
        $users = $DB->get_records_sql($sql, $param_condition);

        $users1 = $DB->get_records('user');

        $tosuspend = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && !isguestuser($user) && $this->shall_suspend($user)) {
                $suspenduser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    $this->get_name()
                );
                $tosuspend[$key] = $suspenduser;
                $this->log("[get_to_suspend] " . $user->username . " marked");
            }
        }

        if (empty($sql_condition) && (count($users) == count($tosuspend)) && (count($users) > 10)) {
            // Check if number of users to suspend equals number of users matching
            // the authentication method filter
            global $DB;
            $count = $DB->count_records_select('user', $this->get_auth_sql(''));
            if ($count == count($tosuspend)) {
                throw new \moodle_exception("warning: all current users shall be suspended => suspension aborted");
            }
        }
        $this->log("[get_to_suspend] marked " . count($tosuspend) . " users");
        return $tosuspend;
    }

/*
    public function get_never_logged_in() {
        global $DB;
        $arrayofuser = $DB->get_records_sql(
            "SELECT u.id, u.suspended, u.lastaccess, u.username, u.deleted, u.auth
                FROM {user} u
                LEFT JOIN {tool_cleanupusers} tc ON u.id = tc.id
                WHERE " . $this->get_auth_sql('u.') . "
                    u.lastaccess = 0
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
                $user->deleted,
                $user->auth,
                $this->get_name())
            ;
            $neverloggedin[$key] = $informationuser;
        }

        return $neverloggedin;
    }*/

    /**
     * All users who should be deleted will be returned in the array.
     * The array includes merely the necessary information which comprises the userid, lastaccess, suspended, deleted
     * and the username.
     * The function only checks the tool_cleanupusers_archive table.
     * Therefore users who are suspended manually are NOT screened.
     *
     * @return array of users who should be deleted.
     */
    public function get_to_delete() {
        $sqlarray = self::get_to_delete_sql($this->get_name());
        if (count($sqlarray) == 0) {
            return [];
        }

        global $DB;
        $sql = 'select * from ' . $sqlarray['from'] . ' where ' . $sqlarray['where'];
        $users = $DB->get_records_sql($sql);

        $todelete = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && !isguestuser($user)) {
                $deleteuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    $this->get_name()
                );
                $todelete[$key] = $deleteuser;
            }
        }
        return $todelete;
    }
    /*
    public function get_to_delete() {
        if ($this->get_deletetime() < 0) {
            // No delete time configured => skip.
            return [];
        }

        global $DB;

        $users = $DB->get_records_sql(
            "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted, tca.auth
                FROM {user} u
                JOIN {tool_cleanupusers} tc ON u.id = tc.id AND tc.checker = :checker
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                WHERE " . $this->get_auth_sql('u.') . "
                    AND u.suspended = 1
                    AND u.deleted = 0
                    AND tc.timestamp < :timelimit",
            [
                'timelimit'  => time() - $this->get_deletetime_in_sec(),
                'checker' => $this->get_name()
            ]
        );

        // debugging($this->name);
        // debugging("get_to_delete 1");
        // var_dump($users);
        $todelete = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && !isguestuser($user)) {
                $deleteuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    $this->get_name()
                );
                $todelete[$key] = $deleteuser;
            }
        }

        // get all users who need to be reactivated by this plugin
        // and remove them from the list of users to be deleted.
        // => prevent users from deletion if they shall be reactivated
        if (count($todelete) > 0) {
            $toreactivate = $this->get_to_reactivate();
            foreach ($todelete as $key => $user) {
                if (array_key_exists($key, $toreactivate)) {
                    unset($todelete[$key]);
                }
            }
            // $todelete = array_diff($todelete, $toreactivate);
            // var_dump($todelete);
        }

        return $todelete;
    }
*/

    /**
     * All users who should be deleted will be returned in the array.
     * The array includes merely the necessary information which comprises
     * the userid, lastaccess, suspended, deleted and the username.
     * The function only checks the tool_cleanupusers_archive table.
     * Therefore users who are suspended manually are NOT screened.
     *
     * @return array of users who should be deleted.
     */
    public static function get_to_delete_sql($checker = null) : array {
        // Get list with enabled subplugins.
        if (empty($checker)) {
            $pluginsenabled =  \core_plugin_manager::instance()->get_enabled_plugins("userstatus");
            if (count($pluginsenabled) == 0) {
                \core\notification::warning("No userstatus plugin enabled");
                return [];
            }
        } else {
            $pluginsenabled = [];
            $pluginsenabled[$checker] = $checker;
        }
        // Get delete time value for each subplugin.
        $checkers = [];
        foreach ($pluginsenabled as $subpluginname => $dir) {
            if (empty($subpluginname)) {
                continue;
            }
            $subplugin = "\\userstatus_" . $subpluginname . "\\" . $subpluginname;
            if (!class_exists($subplugin)) {
                // plugin might have been uninstalled?
                continue;
            }
            $subplugin = new $subplugin();
            if ($subplugin->get_deletetime() >= 0) {
                $checkers[] = '(tc.checker=\'' . $subpluginname . '\' 
                and tc.timestamp < '. time() - $subplugin->get_deletetime_in_sec().')';
            }
        }

        if (count($checkers) == 0) {
            \core\notification::warning("No valid and active userstatus plugin enabled for deletion");
            return [];
        }
        // var_dump($checkers);
        $condition = implode(' OR ', $checkers);

        // Full join means that only users will be handled who are already
        // suspended with the cleanupusers plugin
        $sql = [
            // fields
            'fields' =>
            'tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted, tca.auth, 
                tc.checker, tca.firstname, tca.lastname, tc.timestamp, 
                tca.firstnamephonetic, tca.lastnamephonetic, tca.middlename, tca.alternatename, 
                tca.firstname, tca.lastname',
            // from
            'from' => '{tool_cleanupusers_archive} tca  
                JOIN {tool_cleanupusers} tc ON tc.id = tca.id',
                // JOIN {user} u ON u.id = tca.id // avoid join accross user table because otherwise
                // the user filter does not work (ambiguous attributes)
            // where
            'where' => $condition
//            'where' => 'u.suspended = 1 AND u.deleted = 0 AND (' . $condition . ')'
            ];
        return $sql;
/*
        global $DB;
        $users = $DB->get_records_sql(
            "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, 
                    tca.deleted, tca.auth, tc.checker
                FROM {user} u
                JOIN {tool_cleanupusers} tc ON u.id = tc.id 
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                WHERE 
                    u.suspended = 1
                    AND u.deleted = 0
                    AND (" . $condition . ")"
        );
        var_dump($users);
        return $users;
*/
/*
        // debugging($this->name);
        // debugging("get_to_delete 1");
        // var_dump($users);
        $todelete = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && !isguestuser($user)) {
                $deleteuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    $user->checker
                );
                $todelete[$key] = $deleteuser;
            }
        }
*/
        /*
        // get all users who need to be reactivated by this plugin
        // and remove them from the list of users to be deleted.
        // => prevent users from deletion if they shall be reactivated
        if (count($todelete) > 0) {
            $toreactivate = $this->get_to_reactivate();
            foreach ($todelete as $key => $user) {
                if (array_key_exists($key, $toreactivate)) {
                    unset($todelete[$key]);
                }
            }
            // $todelete = array_diff($todelete, $toreactivate);
            // var_dump($todelete);
        }*/

        // return $todelete;
    }



    /**
     * All users that should be reactivated will be returned.
     *
     * @return array of objects
     * @throws \dml_exception
     * @throws \dml_exception
     */
    public function get_to_reactivate() {
        global $DB;

        list($sql_condition, $param_condition) = $this->condition_reactivate_sql('tca', 'tc');
        $sql = "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted, tca.auth
                FROM {user} u
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                JOIN {tool_cleanupusers} tc ON u.id = tc.id and tc.checker = :checker
                WHERE " . $this->get_auth_sql('u.') . "
                    AND u.suspended = 1
                    AND u.deleted = 0
                    AND tca.username NOT IN
                        (SELECT username FROM {user} WHERE username IS NOT NULL)";
        if (!empty($sql_condition)) {
            $sql .= " AND " . $sql_condition;
        }
        $params = [
            "checker" => $this->name
        ];
        if (is_array($param_condition)) {
            $params = array_merge($params, $param_condition);
        }
        // debugging("get_to_reactivate");
        // debugging($params);
        $users = $DB->get_records_sql($sql, $params);
        // debugging($this->name);

        // var_dump($users);

        $toactivate = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && $this->shall_reactivate($user)) {
                $activateuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    $this->get_name()
                );
                $toactivate[$key] = $activateuser;
            }
        }

        // debugging("get_to_reactivate");
        // var_dump($toactivate);

        return $toactivate;
    }

}
