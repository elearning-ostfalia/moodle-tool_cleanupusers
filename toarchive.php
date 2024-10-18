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


// $pagetitle = get_string('toarchive', 'tool_cleanupusers', $checker);

// $PAGE->set_title(get_string('toarchive', 'tool_cleanupusers'));


// $PAGE->set_heading(get_string('toarchive', 'tool_cleanupusers', $checker));
$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/toarchive.php'), ['checker' => $checker]);

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();
/*
if (!empty($checker)) {
    echo $renderer->get_heading(get_string('toarchive', 'tool_cleanupusers', $plugin->get_displayname()));
} else {
    echo $renderer->get_heading(get_string('toarchive', 'tool_cleanupusers'));
}*/
// echo $renderer->get_heading(get_string('toarchive', 'tool_cleanupusers'));


$userfilter = new \tool_cleanupusers\archiveuser_filtering(false, $action, $checker); // user_filtering();
$userfilter->display();
[$sql, $param] = $userfilter->get_full_sql_filter();

$returnurl = new moodle_url('/admin/tool/cleanupusers/toarchive.php',
    ['action' => $userfilter->get_action(), 'checker' => $userfilter->get_checker()]);

// debugging($userfilter->get_action());
switch ($userfilter->get_action()) {
    case \tool_cleanupusers\not_archive_filter_form::TO_BE_ARCHIVED:
// var_dump($sqlfilter);echo '<br>';
// var_dump($paramfilter);
        $checker = $userfilter->get_checker();
        // Update page URL
        $PAGE->set_url($returnurl);

        $subpluginname = "\\userstatus_" . $checker . "\\" . $checker;
        if (!class_exists($subpluginname)) {
            core\notification::warning($subpluginname . ' does not exist');
        } else {
            // var_dump($checker);
            $userstatuschecker = new $subpluginname();
            $PAGE->set_title(get_string('toarchiveby', 'tool_cleanupusers', $userstatuschecker->get_displayname()));
            // echo $renderer->get_heading(get_string('toarchiveby', 'tool_cleanupusers', $userstatuschecker->get_displayname()));
            // debugging($userstatuschecker->get_displayname());

            $archivearray = $userstatuschecker->get_to_suspend();
            if ($sql != null && $sql != '') {
                $sql .= ' AND ' . archiveuser_filtering::users_to_sql_filter($archivearray);
            } else {
                $sql = archiveuser_filtering::users_to_sql_filter($archivearray);
            }
            $archivetable = new \tool_cleanupusers\table\users_table(
                'tool_cleanupusers_toarchive_table',
                $sql, $param, "suspend", $userstatuschecker->get_name(), $returnurl);
            $archivetable->define_baseurl($PAGE->url);
            $archivetable->out(20, false);
        }
        break;
/*
    case \tool_cleanupusers\not_archive_filter_form::MANUALLY_SUSPENDED:
        if (!empty($sql)) {
            $sql .= ' AND suspended = 1';
        } else {
            $sql = 'suspended = 1';
        }
        $archivetable = new \tool_cleanupusers\table\users_table(
            'tool_cleanupusers_toarchive_table',
            $sql, $param, "suspend", 'manually suspended', $returnurl);

        $archivetable->define_baseurl($PAGE->url);
        $archivetable->out(20, false);
        break;*/
    default:
        throw new coding_exception('invalid action');

}

echo $content;
echo $OUTPUT->footer();
