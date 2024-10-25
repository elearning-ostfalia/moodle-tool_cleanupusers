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
 * Web interface to cleanupusers.
 *
 * @package    tool_cleanupusers
 * @copyright  2016 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cleanupusers\plugininfo\userstatus;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Get URL parameters.

$PAGE->set_context(context_system::instance());
$context = context_system::instance();
// Check permissions.
require_admin();

admin_externalpage_setup('cleanupusers');



$pagetitle = get_string('pluginname', 'tool_cleanupusers');
$PAGE->set_title(get_string('pluginname', 'tool_cleanupusers'));
// $PAGE->set_heading(get_string('pluginname', 'tool_cleanupusers'));
$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/pending.php'));

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();
//echo $renderer->get_heading();

$enabled = userstatus::get_enabled_plugins();

$content = $OUTPUT->heading(get_string('pendingactions', 'tool_cleanupusers'), 2, 'main');

if (!$enabled or count($enabled) == 0) {
    core\notification::warning("Note: no userstatus plugin enabled");
} else {
    // Request arrays from the sub-plugin.
    foreach ($enabled as $subplugin => $dir) {
        if (empty($subplugin)) {
            continue;
        }

        $mysubpluginname = "\\userstatus_" . $subplugin . "\\" . $subplugin;
        if (!class_exists($mysubpluginname)) {
            // core\notification::warning($subplugin . ' does not exist');
            continue;
        }

        $userstatuschecker = new $mysubpluginname();

        try {
            $archivearray = $userstatuschecker->get_to_suspend();
            $arraytodelete = $userstatuschecker->get_to_delete();
            $arrayneverloggedin = [];
            $arrayreactivate = $userstatuschecker->get_to_reactivate();

            if (count($archivearray) > 0 || count($arraytodelete) > 0 || count($arrayreactivate)) {
                $content .= $OUTPUT->heading($userstatuschecker->get_displayname(), 4, 'main');
                $content .= $renderer->render_preview_page($arrayreactivate, $archivearray,
                    $arraytodelete, $subplugin);
            }
        } catch (Exception $e) {
            core\notification::warning($subplugin . ': '. $e->getMessage());
            // throw $e;
        }
    }
}


echo $content;
echo $OUTPUT->footer();
