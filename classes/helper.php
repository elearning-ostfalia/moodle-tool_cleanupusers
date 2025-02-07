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

namespace tool_cleanupusers;

use tool_cleanupusers\plugininfo\userstatus;

/**
 * Helper functions
 *
 * @package   tool_cleanupusers
 * @copyright 2024 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * return array with all enabled subplugins. Key ist pluginname,
     * value is plugin display name
     *
     * @return array
     */
    public static function get_enabled_checkers_with_displayname() {
        $enabled = userstatus::get_enabled_plugins();
        $authsavailable = \core_plugin_manager::instance()->get_plugins_of_type('userstatus');

        foreach ($authsavailable as $auth => $dir) {
            if (!array_key_exists($auth, $enabled)) {
                continue;
            }
            $enabled[$auth] = $dir->displayname;
        }
        return $enabled;
    }

    /**
     * render inplace editable for auth method input in general settings table
     *
     * @param $plugin
     * @param mixed $newvaluetext
     * @param mixed $newvalue1
     * @return \core\output\inplace_editable
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function render_auth_editable($plugin, string $newvaluetext, $newvalue1): \core\output\inplace_editable {
        $auths = get_enabled_auth_plugins();
        $templ = new \core\output\inplace_editable(
            'tool_cleanupusers',
            'authmethod',
            $plugin,
            has_capability('moodle/site:config', \context_system::instance()),
            empty(trim($newvaluetext)) ? get_string('all-authmethods', 'tool_cleanupusers') : $newvaluetext,
            $newvalue1,
            get_string('authmethod_info', 'tool_cleanupusers'),
            get_string('authmethod', 'tool_cleanupusers')
        );
        $attributes = ['multiple' => true];
        $templ->set_type_autocomplete($auths, $attributes);
        return $templ;
    }

    /**
     * render inplace editable for delete time input in general settings table     *
     * @param $plugin
     * @param mixed $newvalue1
     * @return \core\output\inplace_editable
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function render_deletetime_editable($plugin, mixed $newvalue): \core\output\inplace_editable {
        return new \core\output\inplace_editable(
            'tool_cleanupusers',
            'deletetime',
            $plugin,
            has_capability('moodle/site:config', \context_system::instance()),
            $newvalue,
            $newvalue,
            get_string('deletetime', 'tool_cleanupusers'),
            get_string('deletetime', 'tool_cleanupusers')
        );
    }

    /**
     * @param string $pluginname
     * @param mixed $timetosuspend
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function render_suspendtime_editable(string $pluginname, mixed $timetosuspend): \core\output\inplace_editable {
        $mysubpluginname = "\\userstatus_" . $pluginname . "\\" . $pluginname;
        $userstatuschecker = new $mysubpluginname();

        return new \core\output\inplace_editable(
            'tool_cleanupusers',
            'suspendtime',
            $pluginname,
            has_capability('moodle/site:config', \context_system::instance()),
            $timetosuspend,
            $timetosuspend,
            $userstatuschecker->get_suspend_hint(),
            get_string('suspendtime', 'tool_cleanupusers')
        );
    }

    /**
     * @param $plugin
     * @param mixed $newvalue1
     * @return \core\output\inplace_editable
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function render_no_login_editiable($plugin, mixed $newvalue1): \core\output\inplace_editable {
        // Prepare the element for the output:
        $keylist = [];
        $keylist[0] = get_string('suspend', 'tool_cleanupusers');
        $keylist[1] = get_string('delete', 'tool_cleanupusers');
        $templ = new \core\output\inplace_editable(
            'tool_cleanupusers',
            'neverloggedin',
            $plugin,
            has_capability('moodle/site:config', \context_system::instance()),
            null,
            empty($newvalue1) ? 0 : $newvalue1,
            get_string('neverloggedin_info', 'tool_cleanupusers'),
            get_string('neverloggedin_info', 'tool_cleanupusers')
        );
        $templ->set_type_select($keylist);
        return $templ;
    }

    /**
     * Deletes, suspends or reactivates an array of users.
     *
     * @param  array $userarray of users
     * @param  string $intention of suspend, delete, reactivate
     * @return array ['numbersuccess'] successfully changed users ['failures'] userids, who could not be changed.
     * @throws \coding_exception
     */
    public static function change_user_deprovisionstatus($userarray, $intention, $checker, $dryrun = false) {
        // Checks whether the intention is valid.
        if (!in_array($intention, ['suspend', 'reactivate', 'delete'])) {
            throw new \coding_exception('Invalid parameters in tool_cleanupusers.');
        }

        // Number of successfully changed users.
        $countersuccess = 0;

        // Array of users who could not be changed.
        $failures = [];

        // Array of successfully archived users
        $archivedusers = [];

        // Array of successfully deleted users
        $deletedusers = [];

        // Alternatively one could have written different function for each intention.
        // However, this would have produced duplicated code.
        // Therefore, checking the intention parameter repeatedly was preferred.
        foreach ($userarray as $key => $user) {
            global $USER;
            if ($user->deleted == 0 && !is_siteadmin($user) && !isguestuser($user) && $USER->id != $user->id) {
                $changinguser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    $user->email,
                    $user->timecreated,
                    $checker
                );
                try {
                    switch ($intention) {
                        case 'suspend':
                            if (empty($checker)) {
                                throw new \coding_exception('checker name is missing');
                            }
                            $backdate = get_config('tool_cleanupusers', 'backdate');
                            $extra = get_config('tool_cleanupusers', 'backdating_extra');
                            $timestamp = time();
                            if ($backdate && $extra > 0) {
                                // backdate archiving date
                                if (isset($user->lastaccess) && $user->lastaccess > 0) {
                                    // Last login timestamp available => use as base
                                    $timestamp = $user->lastaccess + ($extra * DAYSECS);
                                } else {
                                    // Use creation date as base
                                    if (isset($user->timecreated) && $user->timecreated > 0) {
                                        $timestamp = $user->timecreated + ($extra * DAYSECS);
                                    }
                                }
                            }
                            if ($timestamp > time()) {
                                $timestamp = time();
                            }
                            $archiveduser = $changinguser->archive_me($checker, $dryrun, $timestamp);
                            array_push($archivedusers, $archiveduser);
                            break;
                        case 'reactivate':
                            if (!$dryrun) {
                                $changinguser->activate_me();
                            }
                            break;
                        case 'delete':
                            $deleteduser = $changinguser->delete_me($dryrun);
                            array_push($deletedusers, $deleteduser);
                            break;
                        // No default since if-clause checks the intention parameter.
                    }
                    $countersuccess++;
                } catch (\Throwable $e) {
                    $failures[$key] = $user->id;
                }
            }
        }
        $result = [];
        $result['countersuccess'] = $countersuccess;
        $result['failures'] = $failures;
        $result['archivedusers'] = $archivedusers;
        $result['deletedusers'] = $deletedusers;
        return $result;
    }

    public static function users_to_sql_filter(array $userset, string $prefix = null) {
        if (count($userset) == 0) {
            return "FALSE";
        }

        // create SQL filter from id list
        $idsasstring = '';
        foreach ($userset as $id => $user) {
            $idsasstring .= $user->id . ',';
        }
        $idsasstring = rtrim($idsasstring, ',');
        if (!empty($prefix)) {
            return $prefix . '.id IN (' . $idsasstring . ')';
        } else {
            return 'id IN (' . $idsasstring . ')';
        }
    }

    /**
     * performs the actual user archiving and reactivating with the option
     * to perform a dry run
     *
     * @param array $pluginsenabled
     * @return array
     * @throws \coding_exception
     */
    public static function archive_users($dryrun = false): array {
        $unabletoarchive = [];
        $userarchived = 0;
        $archievdusers = [];

        $unabletoactivate = [];
        $useractivated = 0;

        // wrong order!
        // $pluginsenabled =  \core_plugin_manager::instance()->get_enabled_plugins("userstatus");
        // correct order:
        $pluginsenabled = \tool_cleanupusers\plugininfo\userstatus::get_enabled_plugins();

        if ($pluginsenabled) {
            foreach ($pluginsenabled as $subplugin => $dir) {

                $mysubpluginname = "\\userstatus_" . $subplugin . "\\" . $subplugin;
                $userstatuschecker = new $mysubpluginname();

                $userstatuschecker->invalidate_cache();

                // Private function is executed to suspend, delete and activate users.
                $archivearray = $userstatuschecker->get_to_suspend();
                $reactivatearray = $userstatuschecker->get_to_reactivate();

                $suspendresult = self::change_user_deprovisionstatus($archivearray, 'suspend', $subplugin, $dryrun);
                $unabletoarchive = array_merge($unabletoarchive, $suspendresult['failures']);
                $userarchived += $suspendresult['countersuccess'];
                $archievdusers = array_merge($archievdusers, $suspendresult['archivedusers']);

                $result = self::change_user_deprovisionstatus($reactivatearray, 'reactivate', $subplugin, $dryrun);
                $unabletoactivate = array_merge($unabletoactivate, $result['failures']);
                $useractivated += $result['countersuccess'];
            }
        }
        return array($unabletoarchive, $userarchived, $archievdusers, $unabletoactivate, $useractivated);
    }

    /**
     * performs the actual user archiving and reactivating with the option
     * to perform a dry run
     *
     * @param array $pluginsenabled
     * @return array
     * @throws \coding_exception
     */
    public static function delete_users($dryrun = false): array {
        $unabletodelete = [];
        $userdeleted = 0;
        $deletedusers = [];

        // wrong order!
        // $pluginsenabled =  \core_plugin_manager::instance()->get_enabled_plugins("userstatus");
        // correct order:
        $pluginsenabled = \tool_cleanupusers\plugininfo\userstatus::get_enabled_plugins();

        if ($pluginsenabled) {
            foreach ($pluginsenabled as $subplugin => $dir) {

                $mysubpluginname = "\\userstatus_" . $subplugin . "\\" . $subplugin;
                $userstatuschecker = new $mysubpluginname();

                $userstatuschecker->invalidate_cache();

                // Private function is executed to suspend, delete and activate users.
                $arraytodelete = $userstatuschecker->get_to_delete();
                $deleteresult = self::change_user_deprovisionstatus($arraytodelete, 'delete', $subplugin, $dryrun);
                $unabletodelete = array_merge($unabletodelete, $deleteresult['failures']);
                $deletedusers = array_merge($deletedusers, $deleteresult['deletedusers']);
                $userdeleted += $deleteresult['countersuccess'];
            }
        }
        return array($unabletodelete, $userdeleted, $deletedusers);
    }
}
