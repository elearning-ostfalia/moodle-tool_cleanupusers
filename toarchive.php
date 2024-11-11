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
 * Site to manage users who will be archived in the next cronjob.
 *
 * @package    tool_cleanupusers
 * @copyright  2018 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cleanupusers\archiveuser_filtering;
use tool_cleanupusers\helper;
use tool_cleanupusers\plugininfo\userstatus;

global $CFG, $PAGE, $OUTPUT;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/table/archive_table.php');

// Get URL parameters.
$action  = optional_param('action', null, PARAM_INT);
$checker = optional_param('checker', null, PARAM_ALPHANUMEXT);

$PAGE->set_context(context_system::instance());
// Check permissions.
require_admin();

admin_externalpage_setup('cleanupusers');


$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/toarchive.php'), ['checker' => $checker]);

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();

if (count(userstatus::get_enabled_plugins()) == 0) {
    \core\notification::warning("Note: no userstatus plugin enabled");
    echo $OUTPUT->footer();
    return;
}


$userfilter = new \tool_cleanupusers\archiveuser_filtering(false, $action, $checker);
$userfilter->display();
[$sqlfilter, $param] = $userfilter->get_full_sql_filter();
$checker = $userfilter->get_checker();
$action = $userfilter->get_action();

$returnurl = new moodle_url('/admin/tool/cleanupusers/toarchive.php',
    ['action' => $action, 'checker' => $checker]);

if ($action != \tool_cleanupusers\not_archive_filter_form::TO_BE_ARCHIVED) {
    throw new coding_exception('invalid action');
}

// Update page URL
$PAGE->set_url($returnurl);

$subpluginname = "\\userstatus_" . $checker . "\\" . $checker;
if (!class_exists($subpluginname)) {
    core\notification::warning($subpluginname . ' does not exist');
} else {
    $userstatuschecker = new $subpluginname();
    $PAGE->set_title(get_string('toarchiveby', 'tool_cleanupusers', $userstatuschecker->get_displayname()));

    $archivearray = $userstatuschecker->get_to_suspend();
    if ($sqlfilter != null && $sqlfilter != '') {
        $sqlfilter .= ' AND ' . helper::users_to_sql_filter($archivearray);
    } else {
        $sqlfilter = helper::users_to_sql_filter($archivearray);
    }
    $archivetable = new \tool_cleanupusers\table\users_table(
        'tool_cleanupusers_toarchive_table',
        $sqlfilter, $param, $userstatuschecker->get_name(), $returnurl);
    $archivetable->define_baseurl($PAGE->url);
    $archivetable->out(20, false);
}


echo $content;
echo $OUTPUT->footer();
