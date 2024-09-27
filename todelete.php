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
 * @copyright  2018 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/filters/lib.php');

// Get URL parameters.

$PAGE->set_context(context_system::instance());
$context = context_system::instance();
// Check permissions.
require_login();
require_capability('moodle/site:config', $context);

admin_externalpage_setup('cleanupusers');

$pagetitle = get_string('todelete', 'tool_cleanupusers');
$PAGE->set_title(get_string('todelete', 'tool_cleanupusers'));
// $PAGE->set_heading(get_string('todelete', 'tool_cleanupusers'));
$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/todelete.php'));

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();
echo $renderer->get_heading(get_string('todelete', 'tool_cleanupusers'));

$sql = \tool_cleanupusers\userstatuschecker::get_to_delete_sql();

if (count($sql) > 0) {

// $pluginsenabled =  \core_plugin_manager::instance()->get_enabled_plugins("userstatus");

    $userfilter = new user_filtering();
    $userfilter->display_add();
    $userfilter->display_active();
    [$sqlfilter, $paramfilter] = $userfilter->get_sql_filter();

    /*
    $deletearray = [];

    foreach ($pluginsenabled as $subplugin => $dir) {
        $mysubpluginname = "\\userstatus_" . $subplugin . "\\" . $subplugin;
        $userstatuschecker = new $mysubpluginname();

        // Request arrays from the sub-plugin.
        $result = $userstatuschecker->get_to_delete();
        if (empty($deletearray)) {
            echo "Currently no users will be deleted by the next cronjob for checker " .
                $userstatuschecker->get_displayname() . ".<br>";
        }
        $deletearray = array_merge($deletearray, $result);
    }
    if (count($deletearray) > 0) {
        var_dump($sql);
        var_dump($deletearray);
        if (!empty($sql)) {
            $sql .= 'and ';
        }
        $sql .= 'id in (' . implode(',', array_keys($deletearray)) . ')';
        var_dump($sql);
        if (!empty($sql)) {
    */
    $deletetable = new \tool_cleanupusers\table\reactivate_table('tool_cleanupusers_todelete_table',
        $sqlfilter, $paramfilter, "delete", $sql);
//            $deletearray, $sql, $param, "delete");

    $deletetable->define_baseurl($PAGE->url);
    $deletetable->out(20, false);
// }}
}

echo $content;
echo $OUTPUT->footer();
