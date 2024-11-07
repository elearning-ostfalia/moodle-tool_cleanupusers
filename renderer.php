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
 * Renderer for the Web interface of tool_cleanupusers.
 *
 * @package    tool_cleanupusers
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cleanupusers\archiveduser;
use tool_cleanupusers\helper;
use tool_cleanupusers\not_archive_filter_form;
use tool_cleanupusers\archive_filter_form;
use tool_cleanupusers\plugininfo\userstatus;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/classes/not_archive_filter_form.php');
require_once(__DIR__ . '/classes/archive_filter_form.php');

/**
 * Class of the tool_cleanupusers renderer.
 *
 * @package    tool_cleanupusers
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_cleanupusers_renderer extends plugin_renderer_base {

    public function render_subplugin_table(): string {
        global $OUTPUT, $DB, $CFG;
        // display strings
        $txt = get_strings(array('authenticationplugins',
            'settings', 'edit', 'name', 'enable', 'disable',
            'up', 'down', 'none'));
        $txt->updown = "$txt->up/$txt->down";
        $txt->authmethod = get_string('authmethod', 'tool_cleanupusers');
        $txt->condition = get_string('condition', 'tool_cleanupusers');
        $txt->deletetime = get_string('deletetime', 'tool_cleanupusers');
        $txt->suspendtime = get_string('suspendtime', 'tool_cleanupusers');
        $txt->deleteifneverloggedin = get_string('deleteifneverloggedin', 'tool_cleanupusers');

        $authsavailable = core_plugin_manager::instance()->get_plugins_of_type('userstatus');
        $authsenabled = userstatus::get_enabled_plugins();
        if (!$authsenabled) {
            $authsenabled = [];
        }

        // construct the display array, with enabled pluginname plugins at the top, in order
        $displayauths = [];
        foreach ($authsenabled as $auth) {
            $displayauths[$auth] = $auth;
        }
        foreach ($authsavailable as $auth => $dir) {
            if (array_key_exists($auth, $displayauths)) {
                continue; //already in the list
            }
            $displayauths[$auth] = $dir->displayname;
        }

        $return = $OUTPUT->heading(get_string('actpluginshdr', 'tool_cleanupusers'), 3, 'main');
        $return .= $OUTPUT->box_start('generalbox authsui');

        $table = new html_table();
        $table->head  = array($txt->name, $txt->condition, $txt->authmethod,
            $txt->suspendtime, $txt->deletetime, $txt->deleteifneverloggedin,
            $txt->enable, $txt->updown, $txt->settings);
        $table->colclasses = array('leftalign', 'leftalign', 'centeralign', 'centeralign', 'centeralign', 'centeralign', 'centeralign');
        $table->data  = array();
        $table->attributes['class'] = 'admintable generaltable';
        $table->id = 'manageauthtable';

        // iterate through plugins and add to the display table
        $updowncount = 1;
        $authcount = count($authsenabled);
        $url = "index.php?sesskey=" . sesskey();
        foreach ($displayauths as $pluginname => $name) {
            $class = '';
            $mysubpluginname = "\\userstatus_" . $pluginname . "\\" . $pluginname;
            if (!class_exists($mysubpluginname)) {
                // core\notification::warning($pluginname . ' does not exist');
                continue;
            }

            $userstatuschecker = new $mysubpluginname();

            // displayname
            $displayname = $userstatuschecker->get_displayname();
            // hide/show link
            if (in_array($pluginname, $authsenabled)) {
                $hideshow = "<a href=\"$url&amp;action=disable&amp;userstatus=$pluginname\">";
                $hideshow .= $OUTPUT->pix_icon('t/hide', get_string('disable')) . '</a>';
                $enabled = true;
            }
            else {
                $hideshow = "<a href=\"$url&amp;action=enable&amp;userstatus=$pluginname\">";
                $hideshow .= $OUTPUT->pix_icon('t/show', get_string('enable')) . '</a>';
                $enabled = false;
                $class = 'dimmed_text';
            }

            // Condition
            $condition = $userstatuschecker->get_condition_text();

            // Authentication method
            $auths = \get_enabled_auth_plugins();
            $authvalues = $userstatuschecker->get_authentication_method();
            // look for keys in enabled auth methods and convert to json
            $authvalue_sarray = explode(',', $authvalues);
            $keylist = [];
            foreach ($authvalue_sarray as $key => $authvalue) {
                $keylist[] = array_search($authvalue, $auths);
            }

            $strkeylist = json_encode($keylist);
            $authmethod = $OUTPUT->render(\tool_cleanupusers\helper::render_auth_editable($pluginname,
                $authvalues, $strkeylist));

            // Time to suspend
            if ($userstatuschecker->needs_suspendtime()) {
                $timetosuspend = $OUTPUT->render(\tool_cleanupusers\helper::render_suspendtime_editable(
                    $pluginname, $userstatuschecker->get_suspendtime()));
            } else {
                // No suspendtime input
                $timetosuspend = '';
            }

            // Time to delete
            $timetodelete = $OUTPUT->render(\tool_cleanupusers\helper::render_deletetime_editable(
                $pluginname, $userstatuschecker->get_deletetime()));

            // deleteifneverloggedin
            $neverloggendin = $userstatuschecker->delete_if_never_logged_in_on_suspendtime();
            $neverloggendin = $OUTPUT->render(\tool_cleanupusers\helper::render_no_login_editiable(
                $pluginname, $neverloggendin));

            // up/down link (only if pluginname is enabled)
            $updown = '';
            if ($enabled) {
                if ($updowncount > 1) {
                    $updown .= "<a href=\"$url&amp;action=up&amp;userstatus=$pluginname\">";
                    $updown .= $OUTPUT->pix_icon('t/up', get_string('moveup')) . '</a>&nbsp;';
                }
                else {
                    $updown .= $OUTPUT->spacer() . '&nbsp;';
                }
                if ($updowncount < $authcount) {
                    $updown .= "<a href=\"$url&amp;action=down&amp;userstatus=$pluginname\">";
                    $updown .= $OUTPUT->pix_icon('t/down', get_string('movedown')) . '</a>&nbsp;';
                }
                else {
                    $updown .= $OUTPUT->spacer() . '&nbsp;';
                }
                ++ $updowncount;
            }

            // settings link
            if (file_exists( __DIR__ . '/userstatus/'.$pluginname.'/settings.php')) {
                $settings = "<a href=\"{$CFG->wwwroot}/admin/settings.php?section=cleanupusers_userstatus$pluginname\">{$txt->settings}</a>";
            } else {
                $settings = '';
            }

            // Add a row to the table.
            $row = new html_table_row(array($displayname, $condition, $authmethod, $timetosuspend, $timetodelete,
                $neverloggendin, $hideshow, $updown, $settings));
            if ($class) {
                $row->attributes['class'] = $class;
            }
            $table->data[] = $row;
        }
        $return .= html_writer::table($table);
        $return .= $OUTPUT->box_end();
        return $return;
    }
    /**
     * Function expects four arrays and renders them to separate tables.
     *
     * @param array $userstoreactivate
     * @param array $userstosuspend
     * @param array $usertodelete
     * @param array $usersneverloggedin
     * @return string html
     */
    public function render_preview_page($userstoreactivate, $userstosuspend, $usertodelete,
                                        $checker) {
        global $PAGE;
        $returnurl = new \moodle_url('/admin/tool/cleanupusers/pending.php');
        $limit = 15;

        // Checks if one of the given arrays is empty to prevent rendering empty arrays.
        // If not empty renders the information needed.
        // Renders the information for each array in a separate table.
        $output = '';
        if (!empty($userstoreactivate)) {
            $url = new \moodle_url('/admin/tool/cleanupusers/archiveusers.php',
                ['action' => archive_filter_form::TO_BE_REACTIVATED, 'checker' => $checker]);
            $limitedarray = array_slice($userstoreactivate, 0, $limit, true);
            $sqlfilter = helper::users_to_sql_filter($limitedarray, 'a');

            $archivetable = new \tool_cleanupusers\table\archive_table(
                'tool_cleanupusers_pending_reactivate_table',
                $sqlfilter, [], "reactivate", [], $returnurl);
            $output .= $this->output_table($archivetable, $limit, $userstoreactivate,
                get_string('willbereactivated', 'tool_cleanupusers'), $url);
        }
        if (!empty($userstosuspend)) {
            $url = new \moodle_url('/admin/tool/cleanupusers/toarchive.php',
                ['action' => not_archive_filter_form::TO_BE_ARCHIVED, 'checker' => $checker]);
            $limitedarray = array_slice($userstosuspend, 0, $limit, true);
            $archivetable = new \tool_cleanupusers\table\users_table(
                'tool_cleanupusers_pending_suspend_table',
                helper::users_to_sql_filter($limitedarray), [], $checker, $returnurl);
            $output .= $this->output_table($archivetable, $limit, $userstosuspend,
                get_string('willbesuspended', 'tool_cleanupusers'), $url);
        }
        if (!empty($usertodelete)) {
            $url = new \moodle_url('/admin/tool/cleanupusers/archiveusers.php',
                ['action' => archive_filter_form::TO_BE_DELETED, 'checker' => $checker]);
            $limitedarray = array_slice($usertodelete, 0, $limit, true);
            $sqlfilter = helper::users_to_sql_filter($limitedarray, 'a');

            $archivetable = new \tool_cleanupusers\table\archive_table(
                'tool_cleanupusers_pending_delete_table',
                $sqlfilter, [], "delete", [], $returnurl);
            $output .= $this->output_table($archivetable, $limit, $usertodelete,
                get_string('willbedeleted', 'tool_cleanupusers'), $url);
        }

        return $output;
    }

    /**
     * Functions returns the heading for the tool_cleanupusers.
     *
     * @return string
     */
    public function get_heading($text = '') {
        if (!empty($text)) {
            return $this->heading($text);
        } else {
            return $this->heading(get_string('pluginname', 'tool_cleanupusers'));
        }
    }

    /**
     * @param \tool_cleanupusers\table\users_table $archivetable
     * @param int $limit
     * @param array $userarray
     * @param string $title
     * @param moodle_url $url
     * @return string
     * @throws coding_exception
     */
    private function output_table($archivetable, int $limit, array $userarray, string $title, moodle_url $url): string
    {
        global $PAGE;
        $archivetable->define_baseurl($PAGE->url);

        $output = \html_writer::tag('h5', $title);
        $output .= $archivetable->get_content($limit);
        if (count($userarray) > $limit) {
            $output .= \html_writer::link($url, '... watch full table ');
        }
        return $output;
    }
}

