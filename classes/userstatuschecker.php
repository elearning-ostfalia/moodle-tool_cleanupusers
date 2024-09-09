<?php

namespace tool_cleanupusers;

/**
 * base class for all subplugin classes
 */
abstract  class userstatuschecker
{
    protected $baseconfig;

    protected $config;

    public function __construct($name, $testing = false) {

        $this->baseconfig = get_config('tool_cleanupusers');

        $class_parts = explode('\\', $name);
        $name = end($class_parts);

        $this->config = get_config('userstatus_' . $name);
        // var_dump($this->config);

//        // Calculates days to seconds.
//        $this->timedelete = $this->config->deletetime * 86400;
//        $this->testing = $testing;
    }


    /**
     * returns the condition for the subplugin
     *
     * @return string
     */
    public function get_condition_text() : string {
        return 'TODO condition ';
    }

    public function get_name() : string {
        return 'TODO name';
    }

    /**
     * check if the given user fulfills the suspension condition
     *
     * @param $user
     * @return false
     */
    abstract public function shall_suspend($user) : bool;

    abstract public function condition_sql() : array;

    /**
     * returns the authentication method for all users being handled by this plugin
     * @return string
     */
    public function get_authentication_method() :string {
        return $this->config->auth_method;
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

        list($sql_condition, $param_condition) = $this->condition_sql();
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
                    $user->auth
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
                $user->deleted);
            $neverloggedin[$key] = $informationuser;
        }

        return $neverloggedin;
    }

}
