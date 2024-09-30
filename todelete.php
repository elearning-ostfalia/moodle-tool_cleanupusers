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
 * Site to manage users who will be deleted in the next cronjob
 *
 * @package    tool_cleanupusers
 * @copyright  2016 N Herrmann, 2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/filters/lib.php');

// Get URL parameters.

$PAGE->set_context(context_system::instance());
$context = context_system::instance();
// Check permissions.
require_admin();

admin_externalpage_setup('cleanupusers');

$pagetitle = get_string('todelete', 'tool_cleanupusers');
$PAGE->set_title(get_string('todelete', 'tool_cleanupusers'));
// $PAGE->set_heading(get_string('todelete', 'tool_cleanupusers'));
$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/todelete.php'));

$renderer = $PAGE->get_renderer('tool_cleanupusers');

echo $OUTPUT->header();
echo $renderer->get_heading(get_string('todelete', 'tool_cleanupusers'));

$content = '';

/*
$mform = new \tool_cleanupusers\subplugin_select_form();
$checker = null;
if ($formdata = $mform->get_data()) {
    // debugging("get form data");
    $arraydata = get_object_vars($formdata);
    if ($mform->is_validated()) {
        $checker = $arraydata['subplugin'];
       // debugging($checker);
    }
}
$mform->display();
*/

$checker = '';
// var_dump($sql);
// $sql = \tool_cleanupusers\userstatuschecker::get_to_delete_sql($checker);

// if (count($sql) > 0) {
    $userfilter = new \tool_cleanupusers\archiveuser_filtering(); // user_filtering();
    $userfilter->display();
/*    $userfilter->display_add();
    $userfilter->display_active();*/
    [$sqlfilter, $paramfilter] = $userfilter->get_sql_filter();
    // var_dump($sqlfilter);echo '<br>';
    // var_dump($paramfilter);
    $sql = \tool_cleanupusers\userstatuschecker::get_to_delete_sql($userfilter->get_checker());

    $deletetable = new \tool_cleanupusers\table\reactivate_table('tool_cleanupusers_todelete_table',
        $sqlfilter, $paramfilter, "delete", $sql);

    $deletetable->define_baseurl($PAGE->url);
    $deletetable->out(20, false);
// }

echo $content;
echo $OUTPUT->footer();
