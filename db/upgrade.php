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
 * Upgrade for the tool_cleanupusers.
 *
 * @package tool_cleanupusers
 * @copyright 2016/17 N Herrmann
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade for the tool_cleanupusers.
 * @package tool_cleanupusers
 * @copyright 2023 my-curiosity
 * @param int $oldversion
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_tool_cleanupusers_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2018021401) {
        // Define field moodlenetprofile to be added to tool_cleanupusers_archive.
        $table = new xmldb_table('tool_cleanupusers_archive');
        $field = new xmldb_field('moodlenetprofile', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'alternatename');

        // Conditionally launch add field moodlenetprofile.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Cleanupusers savepoint reached.
        upgrade_plugin_savepoint(true, 2018021401, 'tool', 'cleanupusers');
    }

    if ($oldversion < 2023061300) {
        // Define field icq to be dropped from tool_cleanupusers_archive.
        $table = new xmldb_table('tool_cleanupusers_archive');
        $field = new xmldb_field('icq');

        // Conditionally launch drop field icq.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field skype to be dropped from tool_cleanupusers_archive.
        $table = new xmldb_table('tool_cleanupusers_archive');
        $field = new xmldb_field('skype');

        // Conditionally launch drop field skype.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field yahoo to be dropped from tool_cleanupusers_archive.
        $table = new xmldb_table('tool_cleanupusers_archive');
        $field = new xmldb_field('yahoo');

        // Conditionally launch drop field yahoo.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field aim to be dropped from tool_cleanupusers_archive.
        $table = new xmldb_table('tool_cleanupusers_archive');
        $field = new xmldb_field('aim');

        // Conditionally launch drop field aim.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field msn to be dropped from tool_cleanupusers_archive.
        $table = new xmldb_table('tool_cleanupusers_archive');
        $field = new xmldb_field('msn');

        // Conditionally launch drop field msn.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field url to be dropped from tool_cleanupusers_archive.
        $table = new xmldb_table('tool_cleanupusers_archive');
        $field = new xmldb_field('url');

        // Conditionally launch drop field url.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Cleanupusers savepoint reached.
        upgrade_plugin_savepoint(true, 2023061300, 'tool', 'cleanupusers');
    }

    if ($oldversion < 2024090900) {
        $table = new xmldb_table('tool_cleanupusers');
        $field = new xmldb_field('checker', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'timestamp');

        // Add checker column to table tool_cleanupusers.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Set value for existing rows.
            $config = get_config('tool_cleanupusers', 'cleanupusers_subplugin');
            if ($config) {
                $subplugin = $config;
            } else {
                $subplugin = 'timechecker';
            }

            global $DB;
            if (!$DB->execute("UPDATE {tool_cleanupusers} SET checker = :checker", ['checker' => $subplugin])) {
                echo 'Could not set checker column in table tool_cleanupusers <br>' . PHP_EOL;
            }

            // Now make the field notnull.
            $field = new xmldb_field('checker', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'timestamp');
            $dbman->change_field_notnull($table, $field);
        }

        // Cleanupusers savepoint reached.
        upgrade_plugin_savepoint(true, 2024090900, 'tool', 'cleanupusers');
    }

    if ($oldversion < 2024102200) {
        // Rename timechecker to lastloginchecker
        $subpluginold = 'timechecker';
        $subpluginnew = 'lastloginchecker';

        global $DB;
        if (!$DB->execute("UPDATE {tool_cleanupusers} SET checker = :subpluginnew where checker = :subpluginold ",
            ['subpluginnew' => $subpluginnew, 'subpluginold' => $subpluginold])) {
            echo 'Failed to update timechecker name in database table {tool_cleanupusers} <br>' . PHP_EOL;
        }

        // Copy configuration values from timechecker to lastloginchecker.
        $value = get_config('userstatus_timechecker', 'suspendtime');
        if (isset($value)) {
             set_config('suspendtime', $value, 'userstatus_lastloginchecker');
        }

        $value = get_config('userstatus_timechecker', 'deletetime');
        if (isset($value)) {
            set_config('deletetime', $value, 'userstatus_lastloginchecker');
        }

        // Rename general settings (remove ending settings from plugin).
        $value = get_config('tool_cleanupusers_settings', 'suspendusername');
        if (isset($value)) {
            set_config('suspendusername', $value, 'tool_cleanupusers');
        }
        $value = get_config('tool_cleanupusers_settings', 'suspendfirstname');
        if (isset($value)) {
            set_config('suspendfirstname', $value, 'tool_cleanupusers');
        }

        // Cleanupusers savepoint reached.
        upgrade_plugin_savepoint(true, 2024102200, 'tool', 'cleanupusers');
    }

    return true;
}
