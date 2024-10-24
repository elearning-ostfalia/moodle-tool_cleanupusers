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
    static function render_auth_editable($plugin, mixed $newvaluetext, mixed $newvalue1): \core\output\inplace_editable
    {
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
    static function render_deletetime_editable($plugin, mixed $newvalue): \core\output\inplace_editable
    {
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
    static function render_suspendtime_editable(string $pluginname, mixed $timetosuspend): \core\output\inplace_editable
    {
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
    static function render_no_login_editiable($plugin, mixed $newvalue1): \core\output\inplace_editable
    {
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


}