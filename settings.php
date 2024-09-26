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
 * Adds tool_cleanupusers link in admin tree
 *
 * @package    tool_cleanupusers
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add own category for plugin's  and subplugins' settings.
    $ADMIN->add('users', new admin_category('tool_cleanupusers', get_string('pluginname', 'tool_cleanupusers')));

    // Add entry for own settings.
    $ADMIN->add('tool_cleanupusers', new admin_externalpage(
        'cleanupusers',
        get_string('pluginsettingstitle', 'tool_cleanupusers'),
        "$CFG->wwwroot/$CFG->admin/tool/cleanupusers/index.php"
    ));

    $settings = new admin_settingpage('tool_cleanupusers_settings', get_string('sett_title', 'tool_cleanupusers'));
    $settings->add(new admin_setting_configtext(
        'tool_cleanupusers/suspendusername',
        get_string('sett_suspendusername', 'tool_cleanupusers'),
        get_string('sett_suspendusername_description', 'tool_cleanupusers'),
        get_string('suspendusername', 'tool_cleanupusers'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'tool_cleanupusers/suspendfirstname',
        get_string('sett_suspendfirstname', 'tool_cleanupusers'),
        get_string('sett_suspendfirstname_description', 'tool_cleanupusers'),
        get_string('suspendfirstname', 'tool_cleanupusers'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'tool_cleanupusers/suspendlastname',
        get_string('sett_suspendlastname', 'tool_cleanupusers'),
        get_string('sett_suspendlastname_description', 'tool_cleanupusers'),
        '',
        PARAM_TEXT
    ));

    $ADMIN->add('tool_cleanupusers', $settings);

    // Add entry for users to be archived.
    foreach (core_plugin_manager::instance()->get_enabled_plugins('userstatus') as $plugin) {
        $mysubpluginname = "\\userstatus_" . $plugin . "\\" . $plugin;
        $userstatuschecker = new $mysubpluginname();

        $ADMIN->add('tool_cleanupusers', new admin_externalpage(
            'Manage to archive by ' . $userstatuschecker->get_name(),
            get_string('toarchivelink', 'tool_cleanupusers',
                $userstatuschecker->get_displayname()),
            "$CFG->wwwroot/$CFG->admin/tool/cleanupusers/toarchive.php?checker=" . $userstatuschecker->get_name()
        ));
    }
    // Add entry for users to be deleted.
    $ADMIN->add('tool_cleanupusers', new admin_externalpage(
        'Manage to delete',
        get_string('todelete', 'tool_cleanupusers'),
        "$CFG->wwwroot/$CFG->admin/tool/cleanupusers/todelete.php"
    ));

    // Add entry for achived users.
    $ADMIN->add('tool_cleanupusers', new admin_externalpage(
        'Browse acrhived users',
        get_string('reactivate', 'tool_cleanupusers'),
        "$CFG->wwwroot/$CFG->admin/tool/cleanupusers/reactivate.php"
    ));

    // Adds an entry for every sub-plugin with a settings.php.
    foreach (core_plugin_manager::instance()->get_plugins_of_type('userstatus') as $plugin) {
        global $CFG;
        $plugin->load_settings($ADMIN, 'tool_cleanupusers', $hassiteconfig);
    }
}
