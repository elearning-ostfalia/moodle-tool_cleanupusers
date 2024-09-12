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
 * Site to manage users who never logged in.
 *
 * @package    tool_cleanupusers
 * @copyright  2018 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/user/filters/lib.php');

// Get URL parameters.
echo 'not supported anymmore (separate checker)';

$PAGE->set_context(context_system::instance());
$context = context_system::instance();
// Check permissions.
require_login();
require_capability('moodle/site:config', $context);

/*
admin_externalpage_setup('cleanupusers');

$pagetitle = get_string('neverloggedin', 'tool_cleanupusers');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('standard');

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();
echo $renderer->get_heading();

$pluginsenabled =  \core_plugin_manager::instance()->get_enabled_plugins("userstatus");
foreach ($pluginsenabled as $subplugin => $dir) {
    $mysubpluginname = "\\userstatus_" . $subplugin . "\\" . $subplugin;
    $userstatuschecker = new $mysubpluginname();

    // Request arrays from the sub-plugin.
    $neverloggedinarray = $userstatuschecker->get_never_logged_in();

    if (empty($neverloggedinarray)) {
        echo "Currently no users have never logged in.";
    } else {
        echo '<br>' . get_string('neverloggedininfo', 'tool_cleanupusers');

        $userfilter = new user_filtering();
        $userfilter->display_add();
        $userfilter->display_active();
        [$sql, $param] = $userfilter->get_sql_filter();
        $neverloggedintable = new \tool_cleanupusers\table\never_logged_in_table($neverloggedinarray, $sql, $param);
        $neverloggedintable->define_baseurl(new moodle_url('admin/tool/cleanupusers/neverloggedin.php'));
        $neverloggedintable->out(20, false);
    }
}

echo $content;
echo $OUTPUT->footer();
*/