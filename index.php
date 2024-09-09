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
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Get URL parameters.

$PAGE->set_context(context_system::instance());
$context = context_system::instance();
// Check permissions.
require_login();
require_capability('moodle/site:config', $context);

admin_externalpage_setup('cleanupusers');


$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$userstatus   = optional_param('userstatus', '', PARAM_PLUGIN);

$pagetitle = get_string('pluginname', 'tool_cleanupusers');
$PAGE->set_title(get_string('pluginname', 'tool_cleanupusers'));
$PAGE->set_heading(get_string('pluginname', 'tool_cleanupusers'));
$PAGE->set_pagelayout('standard');

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();
echo $renderer->get_heading();
$content = '';

/*
$mform = new \tool_cleanupusers\subplugin_select_form();
if ($formdata = $mform->get_data()) {
    $arraydata = get_object_vars($formdata);
    if ($mform->is_validated()) {
        set_config('cleanupusers_subplugin', $arraydata['subplugin'], 'tool_cleanupusers');
        $content = 'You successfully submitted the subplugin.';
    }
}
$mform->display();
*/


////////////////////////////////////////////////////////////////////////////////
// process actions


// $enabled =  core_plugin_manager::instance()->get_enabled_plugins("userstatus");
$class = \core_plugin_manager::resolve_plugininfo_class('userstatus');
$enabled = $class::get_enabled_plugins();

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
            set_config('userstatus_plugins_enabled', implode(',', $enabled));
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
            set_config('userstatus_plugins_enabled', implode(',', $enabled));
            break;
        default:
            break;
    }
}

$enabled =  core_plugin_manager::instance()->get_enabled_plugins("userstatus");
// var_dump($enabled);

////////////////////////////////////////////////////////////////////////////////
// process actions rnd


$content .= $renderer->render_subplugin_table();

/*
$pluginsavailable = core_plugin_manager::instance()->get_plugins_of_type('userstatus');
foreach ($pluginsavailable as $auth => $dir) {
    var_dump($auth); echo '<br>';
    // var_dump($dir); echo '<br>';
    // $class::enable_plugin($auth, false);
}
*/

$pluginsenabled =  core_plugin_manager::instance()->get_enabled_plugins("userstatus");
if (!$pluginsenabled) {
    core\notification::warning("Note: no userstatus plugin enabled");
} else {
    /*
    // Assures right sub-plugin is used.
    $config = get_config('tool_cleanupusers', 'cleanupusers_subplugin');
    if ($config) {
        $subplugin = $config;
        $mysubpluginname = "\\userstatus_" . $subplugin . "\\" . $subplugin;
        $userstatuschecker = new $mysubpluginname();
    } else {
        $subplugin = 'timechecker';
        $userstatuschecker = new \userstatus_timechecker\timechecker();
    }

    // Informs the user about the currently used plugin.
    $content .= get_string('using-plugin', 'tool_cleanupusers', $subplugin);
    */

    // Request arrays from the sub-plugin.
    // var_dump($pluginsenabled);
    foreach ($pluginsenabled as $subplugin => $dir) {
        // var_dump($subplugin); echo '<br>';
        // var_dump($dir); echo '<br>';
        // $class::enable_plugin($auth, false);
        if (empty($subplugin)) {
            continue;
        }


        $mysubpluginname = "\\userstatus_" . $subplugin . "\\" . $subplugin;
        $userstatuschecker = new $mysubpluginname();

        try {
            $archivearray = $userstatuschecker->get_to_suspend();
            $arraytodelete = $userstatuschecker->get_to_delete();
            $arrayneverloggedin = $userstatuschecker->get_never_logged_in();
            $arrayreactivate = $userstatuschecker->get_to_reactivate();

            $content .= $OUTPUT->heading($subplugin, 3, 'main');
            $content .= $renderer->render_index_page($arrayreactivate, $archivearray,
                $arraytodelete, $arrayneverloggedin, $subplugin);
		} catch (Exception $e) {
            core\notification::warning($subplugin . ': '. $e->getMessage());
        }
    }
}


echo $content;
echo $OUTPUT->footer();
