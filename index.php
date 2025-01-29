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


$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$userstatus   = optional_param('userstatus', '', PARAM_PLUGIN);

$pagetitle = get_string('pluginname', 'tool_cleanupusers');
$PAGE->set_title(get_string('pluginname', 'tool_cleanupusers'));
// $PAGE->set_heading(get_string('pluginname', 'tool_cleanupusers'));
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();
echo $renderer->get_heading();
$content = '';

// process actions
$enabled = userstatus::get_enabled_plugins();
if (!empty($action) && confirm_sesskey()) {
    switch ($action) {
        case 'disable':
            // Remove from enabled list.
            $class = \core_plugin_manager::resolve_plugininfo_class('userstatus');
            $class::enable_plugin($userstatus, false);
            break;

        case 'enable':
            // Add to enabled list.
            $class = \core_plugin_manager::resolve_plugininfo_class('userstatus');
            $class::enable_plugin($userstatus, true);
            break;
        case 'down':
            if (!isset($enabled[$userstatus])) {
                break;
            }
            $enabled = array_flip(array_keys($enabled));
            $current = $enabled[$userstatus];
            if ($current == count($enabled) - 1) {
                break; // already at the end.
            }
            $enabled = array_flip($enabled);
            $enabled[$current] = $enabled[$current + 1];
            $enabled[$current + 1] = $userstatus;
            set_config(CONFIG_ENABLED, implode(',', $enabled));
            break;

        case 'up':
            if (!isset($enabled[$userstatus])) {
                break;
            }
            $enabled = array_flip(array_keys($enabled));
            $current = $enabled[$userstatus];
            if ($current == 0) {
                break; // already at the top
            }
            $enabled = array_flip($enabled);
            $enabled[$current] = $enabled[$current - 1];
            $enabled[$current - 1] = $userstatus;
            set_config(CONFIG_ENABLED, implode(',', $enabled));
            break;
        default:
            break;
    }
    // reload
    $enabled = userstatus::get_enabled_plugins();
}


// process actions end


if ($task = \core\task\manager::get_scheduled_task('\tool_cleanupusers\task\archive_user_task')) {
    if ($task->get_disabled())
    {
        $link = new \moodle_url(
                '/admin/tool/task/scheduledtasks.php',
                array('action' => 'edit', 'task' => get_class($task))
        );
        \core\notification::error(get_string('archivetaskdisabled', 'tool_cleanupusers', $link->out()));

    }
}

if ($task = \core\task\manager::get_scheduled_task('\tool_cleanupusers\task\delete_user_task')) {
    if (!$task->get_disabled())
    {
        $link = new \moodle_url(
                '/admin/tool/task/scheduledtasks.php',
                array('action' => 'edit', 'task' => get_class($task))
        );
        \core\notification::warning(get_string('deletetaskenabled', 'tool_cleanupusers'));
    } else {
        \core\notification::info(get_string('deletetaskdisabled', 'tool_cleanupusers'));

    }
}


$content .= $renderer->render_subplugin_table();

echo $content;
echo $OUTPUT->footer();
