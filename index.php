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


////////////////////////////////////////////////////////////////////////////////
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
                break; //already at the end
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
                break; //already at the top
            }
            $enabled = array_flip($enabled);
            $enabled[$current] = $enabled[$current - 1];
            $enabled[$current - 1] = $userstatus;
            set_config(CONFIG_ENABLED, implode(',', $enabled));
            break;
        default:
            break;
    }
    // unset($enabled);
    // reload
    $enabled = userstatus::get_enabled_plugins();
    // var_dump($enabled);
}


// $enabled =  core_plugin_manager::instance()->get_enabled_plugins("userstatus");
// var_dump($enabled);

////////////////////////////////////////////////////////////////////////////////
// process actions rnd

$content .= $renderer->render_subplugin_table();

$content .= $OUTPUT->heading(get_string('pendingactions', 'tool_cleanupusers'), 2, 'main');

//$pluginsenabled =  core_plugin_manager::instance()->get_enabled_plugins("userstatus");
// var_dump($pluginsenabled);
if (!$enabled) {
    core\notification::warning("Note: no userstatus plugin enabled");
} else {
    // Request arrays from the sub-plugin.
    // var_dump($pluginsenabled);
    foreach ($enabled as $subplugin => $dir) {
        // var_dump($subplugin); echo '<br>';
        // var_dump($dir); echo '<br>';
        // $class::enable_plugin($auth, false);
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
            $arraytodelete = []; // $userstatuschecker->get_to_delete();
            $arrayneverloggedin = [];
//            $arrayneverloggedin = $userstatuschecker->get_never_logged_in();
            $arrayreactivate = $userstatuschecker->get_to_reactivate();

            if (count($archivearray) > 0 || count($arraytodelete) > 0 || count($arrayreactivate)) {
                $content .= $OUTPUT->heading($userstatuschecker->get_displayname(), 3, 'main');
                $content .= $renderer->render_index_page($arrayreactivate, $archivearray,
                    $arraytodelete, $arrayneverloggedin, $subplugin);
            }
		} catch (Exception $e) {
            core\notification::warning($subplugin . ': '. $e->getMessage());
            // throw $e;
        }
    }
}


echo $content;
echo $OUTPUT->footer();
