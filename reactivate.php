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
 * CLI script for reactivating users on the command line.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cleanupusers\helper;

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

$help = "Command line tool for reactivating archived users.

Options:
    -h --help                   Print this help.
    --show-all                  Displays a list of all archived users.
    --users=<user ids>          A comma separated list of users to be reactivated or 'all' if all users shall be reactivated.
    --run                       Execute reactivate. If this option is not set, then the script will be run in a dry mode.
    --showsql                   Show sql queries before they are executed.
    --showdebugging             Show developer level debugging information.
";

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'show-all' => false,
    'users' => false,
    'run' => false,
    'showsql' => false,
    'showdebugging' => false,
], [
    'h' => 'help'
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($help);
    exit(0);
}

if ($options['showdebugging']) {
    set_debugging(DEBUG_DEVELOPER, true);
}

if ($options['showsql']) {
    global $DB;
    $DB->set_debug(true);
}

$pluginman = core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugins();

if ($options['show-all']) {
    global $DB;
    $records = $DB->get_records('tool_cleanupusers_archive', null, 'id', 'id, username, firstname, lastname');
    foreach ($records as $record) {
        cli_writeln($record->id . ': ' . $record->username . ' ' . $record->firstname . ' ' . $record->lastname);
    }

    $count = count($records);
    cli_writeln("There are {$count} users in the archive who can be reactivated.");

    exit(0);
}


/**
 * reactivates a single user
 * @param string $id
 * @param mixed $record
 * @return
 * @throws coding_exception
 */
function reactivate(string $id, mixed $record): void
{
    cli_writeln('Reactivating: ' . $id);
    $userarray[$id] = $record;
    $progress = new progress_trace_buffer(new text_progress_trace(), true);
    $result = helper::change_user_deprovisionstatus($userarray, 'reactivate', '');
    $progress->finished();
    cli_write($progress->get_buffer());
}

if ($options['users']) {
    global $DB;
    if ($options['users'] == 'all') {
        // reactivate ALL users
        $records = $DB->get_records('tool_cleanupusers_archive', null, 'id',
            'id, username, firstname, lastname, suspended, lastaccess, auth, deleted');
        if ($options['run']) {
            $input = cli_input('Are you sure you wish to reactivate all users from archive? (y/N)', 'N', ['y', 'Y', 'n', 'N']);
            if (strtolower($input) != 'y') {
                exit(0);
            }
            foreach ($records as $record) {
                reactivate($record->id, $record);
            }
        } else {
            foreach ($records as $record) {
                cli_writeln('Will be reactivated: ' . $record->id . ' => ' . $record->username);
            }
        }
    } else {
        $users = explode(',', $options['users']);
        foreach ($users as $id) {
            $record = $DB->get_record('tool_cleanupusers_archive', ['id' => $id],
                'id, username, firstname, lastname, suspended, lastaccess, auth, deleted');
            if ($record === FALSE) {
                cli_writeln('Unknown user: ' . $id);
            } else {
                if ($options['run']) {
                    reactivate($id, $record);
                } else {
                    cli_writeln('Will be reactivated: ' . $id . ' => ' . $record->username);
                }
            }
        }
    }

    exit(0);
}

cli_writeln($help);
exit(0);
