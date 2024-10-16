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
class tools {

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

}