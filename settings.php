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

    // Setting to enable/disable backdating.
    $settings->add(new admin_setting_configcheckbox('tool_cleanupusers/backdate',
        get_string('sett_backdate', 'tool_cleanupusers'),
        get_string('sett_backdate_description', 'tool_cleanupusers'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'tool_cleanupusers/backdating_extra',
        get_string('sett_backdate_extra', 'tool_cleanupusers'),
        get_string('sett_backdate_extra_description', 'tool_cleanupusers'),
        '',
        PARAM_INT
    ));
    $settings->hide_if('tool_cleanupusers/backdating_extra',
        'tool_cleanupusers/backdate', 'neq', '1');

    $settings->add(new admin_setting_pickroles('tool_cleanupusers/teacherroles',
            get_string('teacherroles', 'tool_cleanupusers'),
            get_string('teacherroles_info', 'tool_cleanupusers'),
            array('editingteacher')));

    // Log folder.
    $settings->add(new admin_setting_configtext('userstatus_ldapchecker/log_folder',
            get_string('sett_log_folder', 'tool_cleanupusers'),
            get_string('sett_log_folder_description', 'tool_cleanupusers'),
            '', PARAM_RAW_TRIMMED));


    // add settings pages
    $ADMIN->add('tool_cleanupusers', $settings);
    // Adds an entry for every sub-plugin with a settings.php.
    foreach (core_plugin_manager::instance()->get_plugins_of_type('userstatus') as $plugin) {
        global $CFG;
        $plugin->load_settings($ADMIN, 'tool_cleanupusers', $hassiteconfig);
    }

    // Add entry for users to be archived.
    /*
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
    */

    // Add link to pending actions overview.
    $ADMIN->add('tool_cleanupusers', new admin_externalpage(
        'Cleanupusers Pending actions',
        get_string('pendingactions', 'tool_cleanupusers'),
        "$CFG->wwwroot/$CFG->admin/tool/cleanupusers/pending.php"
    ));

    $ADMIN->add('tool_cleanupusers', new admin_externalpage(
        'Cleanupusers Manage to archive',
        get_string('toarchivelink', 'tool_cleanupusers'),
        "$CFG->wwwroot/$CFG->admin/tool/cleanupusers/toarchive.php"
    ));

    // Add entry for achived users.
    $ADMIN->add('tool_cleanupusers', new admin_externalpage(
        'Cleanupusers Browse acrhived users',
        get_string('reactivate', 'tool_cleanupusers'),
        "$CFG->wwwroot/$CFG->admin/tool/cleanupusers/archiveusers.php"
    ));


}
