<?php

namespace tool_cleanupusers;

/**
 * base class for all subplugin classes
 */
abstract  class userstatuschecker
{
    protected $baseconfig;

    protected $config;

    protected $name;

    /** @var int seconds until a user should be suspended */
    // private $timesuspend;
    /** @var int seconds until a user should be deleted */
    // private $timedelete;

    public function __construct($name, $testing = false) {

        $this->baseconfig = get_config('tool_cleanupusers');

        $class_parts = explode('\\', $name);
        $name = end($class_parts);

        $this->name = $name;
        $this->config = get_config('userstatus_' . $name);
        // var_dump($this->config);

        // Calculates days to seconds.
        // $this->timedelete = $this->config->deletetime * 86400;
        // $this->timesuspend = $this->config->suspendtime * 86400;
//        $this->testing = $testing;
    }


    /**
     * returns the condition for the subplugin
     *
     * @return string
     */
    public function get_condition_text() : string {
        return get_string('condition', 'userstatus_' . $this->name);
    }

    public function get_name() : string {
        return $this->name;
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
        return ["" , null];
    }

    public function condition_reactivate_sql($tca, $tc) {
        return ['', null];
    }

    /**
     * returns the authentication method for all users being handled by this plugin
     * @return string
     */
    public function get_authentication_method() :string {
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
    public function get_deletetime() : int {
        if (!isset($this->config->deletetime) || $this->config->deletetime == null) {
            // initial state
            return 365;
        }
        return $this->config->deletetime;
    }

    public function get_suspendtime() : int {
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

    protected function log($text) {
        /*
        file_put_contents($this->baseconfig->log_folder . "/debug_log_ldapchecker.log",
            "\n[".date("d-M-Y - H:i ")."] $text " , FILE_APPEND);
        */
    }

    private function get_auth_sql($alias) : string {
        if (empty($this->get_authentication_method()))
            return '';
        return $alias . "auth = '" . $this->get_authentication_method() . "' AND ";
    }

    public function get_to_suspend() {
        global $DB;

        list($sql_condition, $param_condition) = $this->condition_suspend_sql();
        $sql = "SELECT id, suspended, lastaccess, username, deleted, auth
                FROM {user}
                WHERE " . $this->get_auth_sql('') . "
                        suspended = 0
                    AND deleted = 0";
        if (!empty($sql_condition)) {
            $sql .= " AND " . $sql_condition;
        }
        $users = $DB->get_records_sql($sql, $param_condition);

        $tosuspend = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user) && $this->shall_suspend($user)) {
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

        $this->log("[get_to_suspend] marked " . count($tosuspend) . " users");
        return $tosuspend;
    }


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
    }

    /**
     * All users who should be deleted will be returned in the array.
     * The array includes merely the necessary information which comprises the userid, lastaccess, suspended, deleted
     * and the username.
     * The function checks the user table and the tool_cleanupusers_archive table. Therefore users who are suspended by
     * the tool_cleanupusers plugin and users who are suspended manually are screened.
     *
     * @return array of users who should be deleted.
     */
    public function get_to_delete() {
        if ($this->get_deletetime() < 0) {
            // No delete time configured => skip.
            return [];
        }

        global $DB;

        $users = $DB->get_records_sql(
            "SELECT tca.id, tca.suspended, tca.lastaccess, tca.username, tca.deleted, tca.auth
                FROM {user} u
                JOIN {tool_cleanupusers} tc ON u.id = tc.id
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                WHERE " . $this->get_auth_sql('u.') . "
                    u.suspended = 1
                    AND u.deleted = 0
                    AND tc.timestamp < :timelimit 
                    AND tc.checker = :name",
            [
                'timelimit'  => time() - $this->get_deletetime(),
                'name' => $this->get_name()
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
                    $user->deleted,
                    $user->auth,
                    $this->get_name()
                );
                $todelete[$key] = $deleteuser;
            }
        }

        return $todelete;
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
                JOIN {tool_cleanupusers} tc ON u.id = tc.id
                JOIN {tool_cleanupusers_archive} tca ON u.id = tca.id
                WHERE " . $this->get_auth_sql('u.') . "
                    u.suspended = 1
                    AND u.deleted = 0
                    AND tca.username NOT IN
                        (SELECT username FROM {user} WHERE username IS NOT NULL)";
        if (!empty($sql_condition)) {
            $sql .= " AND " . $sql_condition;
        }

        $users = $DB->get_records_sql($sql, $param_condition);

        $toactivate = [];
        foreach ($users as $key => $user) {
            if (!is_siteadmin($user)) {
                $activateuser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    ''
                );
                $toactivate[$key] = $activateuser;
            }
        }

        return $toactivate;
    }

}
