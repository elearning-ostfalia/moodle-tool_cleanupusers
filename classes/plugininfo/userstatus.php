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
 * The Plugins of the type userstatus must return values whether users should be deleted, archived or reactivated.
 *
 * The sub-plugins will be used by the cron-job and manually by the admin to determine the appropriate actions for users.
 *
 * @package   tool_cleanupusers
 * @copyright 2016/17 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers\plugininfo;

use admin_settingpage;
use core\plugininfo\base;

/**
 * The general settings for all sub-plugins of userstatus.
 *
 * Defines the deinstallation settings and adds sub-plugins to the admin tree, if they have a settings.php.
 *
 * @package    tool_cleanupusers
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstatus extends base {
    /**
     * Returns true when sub-plugin can be deleted false when not.
     * Returns false for the timechecker sub-plugin and for any plugin currently in usage, otherwise true.
     * @return bool
     */
    public function is_uninstall_allowed() {
        if ($this->is_standard()) {
            return false;
        }
        // Timechecker is the standard sub-plugin and can not be uninstalled.
        if ($this->name == 'timechecker') {
            return false;
        }
        // In case the sub-plugin is in use, sub-plugin can not be uninstalled.
        $enabled = self::get_enabled_plugins();
        if (isset($enabled[$this->name])) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether sub-plugins have settings.php and adds them to the admin menu.
     * In Case a sub-plugin is added the settings.php has to include all global variables it needs.
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        $ADMIN = $adminroot; // May be used in settings.php.

        if (!$this->is_installed_and_upgraded()) {
            return;
        }

        if (!$hassiteconfig || !file_exists($this->full_path('settings.php'))) {
            return;
        }

        $section = $this->get_settings_section_name();
        $settings = new admin_settingpage($section, $this->displayname, 'moodle/site:config', $this->is_enabled() === false);
        include($this->full_path('settings.php')); // This may also set $settings to null.

        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }

    /**
     * Overrides function from the base class to define section name.
     * @return string
     */
    public function get_settings_section_name() {
        return 'cleanupusers_userstatus' . $this->name;
    }

    /**
     * return all enabled subplugins
     *
     * @return array
     */
    public static function get_enabled_plugins() {
        global $CFG;
        $enabled = [];
        if (isset($CFG->userstatus_plugins_enabled)) {
            foreach (array_map('trim', explode(',', $CFG->userstatus_plugins_enabled)) as $checker) {
                if (!empty($checker)) {
                    // Check if plugin is still available.
                    $mysubpluginname = "\\userstatus_" . $checker . "\\" . $checker;
                    if (!class_exists($mysubpluginname)) {
                        // debugging($checker . ' does not exist anymore');
                        continue;
                    }
                    $enabled[$checker] = $checker;
                }
            }
        }
        return $enabled;
    }

    public static function enable_plugin(string $pluginname, int $enabled): bool {
        global $CFG;

        $haschanged = false;
        $plugins = [];
        if (!empty($CFG->userstatus_plugins_enabled)) {
            $plugins = array_flip(explode(',', $CFG->userstatus_plugins_enabled));
        }

        // Only set value if it's different from the current value.
        if ($enabled && !array_key_exists($pluginname, $plugins)) {
            $plugins[$pluginname] = $pluginname;
            $haschanged = true;
        } else if (!$enabled && array_key_exists($pluginname, $plugins)) {
            unset($plugins[$pluginname]);
            $haschanged = true;
        }

        if ($haschanged) {
            $new = implode(',', array_flip($plugins));
            add_to_config_log('userstatus_plugins_enabled', !$enabled, $enabled, $pluginname);
            set_config('userstatus_plugins_enabled', $new);
            // Reset caches.
            \core_plugin_manager::reset_caches();
            // Resets all userstatus_plugins_enabled caches.
            $syscontext = \context_system::instance();
            $syscontext->mark_dirty();
        }

        return $haschanged;
    }


/*
    public static function get_enabled_plugins() {
        global $DB;
        // echo "userstatus.get_enabled_plugins <br>";

        $plugins = \core_plugin_manager::instance()->get_installed_plugins('userstatus');
        if (!$plugins) {
            return [];
        }
        // var_dump($plugins);
        $installed = array();
        foreach ($plugins as $plugin => $version) {
            $installed[] = $plugin.'_disabled';
        }

        list($installed, $params) = $DB->get_in_or_equal($installed, SQL_PARAMS_NAMED);
        $disabled = $DB->get_records_select('config_plugins', "name $installed AND plugin = 'userstatus'", $params, 'plugin ASC');
        foreach ($disabled as $conf) {
            if (empty($conf->value)) {
                continue;
            }
            $name = substr($conf->name, 0, -9);
            unset($plugins[$name]);
        }

        $enabled = array();
        foreach ($plugins as $plugin => $version) {
            $enabled[$plugin] = $plugin;
        }

        return $enabled;
    }


*/
}
