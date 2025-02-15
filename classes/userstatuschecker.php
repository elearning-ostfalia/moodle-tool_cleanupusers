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

define('CONFIG_NEVER_LOGGED_IN', 'deleteifneverloggedin');
define('CONFIG_ENABLED', 'userstatus_plugins_enabled');
define('CONFIG_AUTH_METHOD', 'auth_method');
define('CONFIG_DELETETIME', 'deletetime');
define('CONFIG_SUSPENDTIME', 'suspendtime');
define('CONFIG_LOG_FOLDER', 'log_folder');

/**
 * base class for all subplugin classes
 *
 * @package    userstatus_ldapchecker
 * @copyright  2016/17 N Herrmann, 2024 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class userstatuschecker {
    protected $baseconfig;

    protected $config;

    protected $name;

    public function __construct($name, $testing = false) {

        $this->baseconfig = get_config('tool_cleanupusers');

        $classparts = explode('\\', $name);
        $name = end($classparts);

        $this->name = $name;
        $this->config = get_config('userstatus_' . $name);
    }

    /**
     * checks if the teacher roles are defined and the given user is a teacher in one
     * of the courses he or she is enrolled into.
     *
     * @param $user
     * @param null $courses Reading all courses is very expensive. So it can be given as argument
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected static function is_teacher($user, $courses = null) : bool {
        // Check if user is enrolled as teacher in one of his or her courses.
        // Get all teacher roles
        $roles = get_config('tool_cleanupusers', 'teacherroles');
        if ($roles !== false) {
            $roleids = explode(',', $roles);
            // Get all courses the user is enrolled into
            if (!isset($courses)) {
                // Read
                $courses = enrol_get_all_users_courses($user->id, false, "id");
            }
            foreach ($courses as $course) {
                foreach($roleids as $roleid) {
                    // Get all users who are enrolled in course as teacher
                    $users = get_role_users($roleid, \context_course::instance($course->id));
                    foreach($users as $courseparticipant) {
                        // check if user belongs to teachers
                        // (seems to be more complicated then needed)
                        if ($courseparticipant->id == $user->id) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * returns the condition for the subplugin
     *
     * @return string
     */
    public function get_condition_text(): string {
        return get_string('condition', 'userstatus_' . $this->name);
    }

    public function get_suspend_hint(): string {
        return get_string('suspendtime', 'userstatus_' . $this->name);
    }

    /**
     * e.g. for use in LDAP to invalidate the cache when the task is executed
     * @return void
     */
    public function invalidate_cache(): void {
    }
    /**
     * shortname for internal purposes
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * name of subplugin shown to the user
     * @return string
     * @throws \coding_exception
     */
    public function get_displayname(): string {
        return get_string('pluginname', 'userstatus_' . $this->name);
    }

    /**
     * check if the given user retrived via condition_suspend_sql
     * actually fulfills the suspension condition
     *
     * @param $user
     * @return true
     */
    public function shall_suspend($user): bool {
        return true;
    }

    /**
     * check if the given user fulfills the reactivation condition
     *
     * @param $user
     * @return true
     */
    public function shall_reactivate($user): bool {
        return true;
    }

    /**
     * part of SQL where clause to perform the check for suspension
     * @return array
     */
    public function condition_suspend_sql(): array {
        return ["" , null];
    }

    /**
     * @param $tca string: alias for archive table in SQL from clause
     * @param $tc string: alias for main cleanup table in SQL from clause
     * @return array
     */
    public function condition_reactivate_sql($tca, $tc) {
        return ['', null];
    }

    /**
     * returns the authentication method for all users being handled by this plugin
     * @return string
     */
    public function get_authentication_method(): string {

        if (!isset($this->config) || !isset($this->config->{CONFIG_AUTH_METHOD})) {
            return '';
        }
        return $this->config->{CONFIG_AUTH_METHOD};
    }

    /**
     * whether or not the suspendtime field is needed
     * @return bool
     */
    public function needs_suspendtime(): bool {
        return true;
    }
    /**
     * returns the period after suspension before deletion [days]
     * @return string
     */
    public function get_deletetime(): float {
        if (!isset($this->config->{CONFIG_DELETETIME}) || ($this->config->{CONFIG_DELETETIME} == null)) {
            // initial state
            return 365;
        }
        return $this->config->{CONFIG_DELETETIME};
    }

    public function get_suspendtime(): float {
        if (!isset($this->config->{CONFIG_SUSPENDTIME}) || ($this->config->{CONFIG_SUSPENDTIME} == null)) {
            // initial state
            return 365;
        }
        return $this->config->{CONFIG_SUSPENDTIME};
    }

    public function get_deletetime_in_sec(): int {
        return $this->get_deletetime() * 86400;
    }

    public function get_suspendtime_in_sec(): int {
        return $this->get_suspendtime() * 86400;
    }

    /**
     * what shall be done if a user is about to be suspended (archived) but
     * he or she has never logged in
     * @return bool
     */
    public function delete_if_never_logged_in_on_suspendtime(): bool {
        if (!isset($this->config->{CONFIG_NEVER_LOGGED_IN}) || ($this->config->{CONFIG_NEVER_LOGGED_IN} == null)) {
            return false;
        }
        return $this->config->{CONFIG_NEVER_LOGGED_IN};
    }

    protected function log($text) {
        if (!empty($this->baseconfig->log_folder)) {
            if (!file_exists($this->baseconfig->log_folder)) {
                mkdir($this->baseconfig->log_folder, 0777, true);
            }
            file_put_contents($this->baseconfig->log_folder . "/checker.log",
                "\n[".date("d-M-Y - H:i ")."] $text " , FILE_APPEND);
        }
    }

    protected function get_auth_sql($alias): string {
        if (empty($this->get_authentication_method())) {
            return ' true ';
        }
        // In case there are more authentication methods selected
        // the sql string will be e.g.
        // "(u.auth = 'email' or u.auth = 'ldap')"
        $methods = explode(',', $this->get_authentication_method());
        $methods = implode("' or {$alias}auth = '", $methods);

        return  "({$alias}auth = '" . $methods . "')  ";
    }

    /**
     * returns the array of users to be suspended by this sub-plugin
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_to_suspend() {
        global $DB;

        list($sqlcondition, $paramcondition) = $this->condition_suspend_sql();
        // Select users who are not yet deleted, not yet archived and match condition for sub-plugin.
        $sql = "SELECT id, suspended, lastaccess, username, deleted, auth, 
                    firstname, lastname, timecreated, email
                FROM {user}
                WHERE " . $this->get_auth_sql('') . "
                    AND deleted = 0
                    AND id not in (select id from {tool_cleanupusers})";

        if (!empty($sqlcondition)) {
            $sql .= " AND " . $sqlcondition;
        }
        $users = $DB->get_records_sql($sql, $paramcondition);

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
                    $user->email,
                    $user->timecreated,
                    $this->get_name(),
                );
                // Add further attributes neede for export in csv file.
                $suspenduser->firstname = $user->firstname;
                $suspenduser->lastname = $user->lastname;
                $tosuspend[$key] = $suspenduser;
                $this->log("[get_to_suspend] " . $user->username . " marked");
            }
        }

        if (empty($sqlcondition) && (count($users) == count($tosuspend)) && (count($users) > 10)) {
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
        if ($this->get_deletetime() >= 0) {
            $condition = '(tc.timestamp < '. (time() - $this->get_deletetime_in_sec()) .')';
        }

        if ($this->delete_if_never_logged_in_on_suspendtime()) {
            // If the user shall be deleted immediately if he or she has never
            // logged in and is suspended then delete him (or her)
            $condition = '(' . $condition . ' OR  tca.lastaccess = 0)';
        }

        if (empty($condition)) {
            // Do not delete anything if all records would be deleted
            return [];
        }

        global $DB;
        // Full join means that only users will be handled who are already
        // suspended with the cleanupusers plugin
        $sql = 'SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, 
                    tca.deleted, tca.auth, tca.email
                FROM {tool_cleanupusers_archive} tca
                JOIN {tool_cleanupusers} tc ON tc.id = tca.id and tc.checker = :checker
                JOIN {user} u ON u.id = tc.id and u.suspended = 1 and u.deleted = 0
                WHERE ' . $condition;
        $users = $DB->get_records_sql($sql, ["checker" => $this->name]);

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
                    $user->email,
                    null, // timecreated not needed for deletion
                    $this->get_name()
                );
                $todelete[$key] = $deleteuser;
            }
        }

        // get all users who need to be reactivated by this plugin
        // and remove them from the list of users to be deleted.
        // => prevent users from deletion if they shall be reactivated
        $toreactivate = $this->get_to_reactivate();
        return array_diff_key($todelete, $toreactivate);
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

        list($sqlcondition, $paramcondition) = $this->condition_reactivate_sql('tca', 'tc');
        $sql = "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted, 
                    tca.auth, tca.timecreated, tca.email
                FROM {user} u
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                JOIN {tool_cleanupusers} tc ON u.id = tc.id and tc.checker = :checker
                WHERE " . $this->get_auth_sql('u.') . "
                    AND u.suspended = 1
                    AND u.deleted = 0
                    AND tca.username NOT IN
                        (SELECT username FROM {user} WHERE username IS NOT NULL)";
        if (!empty($sqlcondition)) {
            $sql .= " AND " . $sqlcondition;
        }
        $params = [
            "checker" => $this->name,
        ];
        if (is_array($paramcondition)) {
            $params = array_merge($params, $paramcondition);
        }
        $users = $DB->get_records_sql($sql, $params);

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
                    $user->email,
                    $user->timecreated,
                    $this->get_name()
                );
                $toactivate[$key] = $activateuser;
            }
        }

        return $toactivate;
    }
}
