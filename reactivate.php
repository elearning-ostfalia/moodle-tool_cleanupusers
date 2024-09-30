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
 * Site to reactivate users.
 *
 * @package    tool_cleanupusers
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG, $PAGE, $OUTPUT;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/filters/lib.php');

// Get URL parameters.

$PAGE->set_context(context_system::instance());
$context = context_system::instance();
// Check permissions.
require_admin();

admin_externalpage_setup('cleanupusers');

// $pagetitle = get_string('toarchive', 'tool_cleanupusers', $checker);
// $PAGE->set_title(get_string('achivedusers', 'tool_cleanupusers'));
// $PAGE->set_heading(get_string('achivedusers', 'tool_cleanupusers'));
$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/reactivate.php'));

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();
echo $renderer->get_heading(get_string('achivedusers', 'tool_cleanupusers'));

core\notification::warning(get_string('warn_reactivate', 'tool_cleanupusers'));



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


$userfilter = new user_filtering();
$userfilter->display_add();
$userfilter->display_active();
[$sql, $param] = $userfilter->get_sql_filter();
if (!empty($checker)) {
    if (!empty($sql)) {
        $sql .= ' AND checker = \'' . $checker . '\'';
    } else {
        $sql = 'checker = \'' . $checker . '\'';
    }
}
$archivetable = new \tool_cleanupusers\table\reactivate_table('tool_cleanupusers_toarchive_table',
    $sql, $param, "reactivate", []);
$archivetable->define_baseurl($PAGE->url);
$archivetable->out(20, false);

echo $content;
echo $OUTPUT->footer();
