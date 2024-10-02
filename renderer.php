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
use tool_cleanupusers\not_archive_filter_form;
use tool_cleanupusers\archive_filter_form;

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


    public function render_subplugin_table() : string {
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
        // var_dump($authsavailable);
        // $class = \core_plugin_manager::resolve_plugininfo_class('userstatus');
        // $authsenabled = $class::get_enabled_plugins();
        $authsenabled = core_plugin_manager::instance()->get_enabled_plugins("userstatus");
        if (!$authsenabled) {
            $authsenabled = [];
        }

        // construct the display array, with enabled pluginname plugins at the top, in order
        $displayauths = [];
        $registrationauths = array();
        $registrationauths[''] = $txt->disable;
        $authplugins = array();

        foreach ($authsenabled as $auth) {
            $displayauths[$auth] = $auth;
        }

        foreach ($authsavailable as $auth => $dir) {
            if (array_key_exists($auth, $displayauths)) {
                continue; //already in the list
            }
            $displayauths[$auth] = $dir->displayname;


            // $authplugin = get_auth_plugin($pluginname);
            // $authplugins[$pluginname] = $userstatuschecker;
            /// Get the pluginname title (from core or own pluginname lang files)
            // $authtitle = $userstatuschecker; // $userstatuschecker->get_title();
            /// Apply titles
            // $authsenabled[$pluginname] = $pluginname; // $authtitle;
/*            if ($userstatuschecker->can_signup()) {
                $registrationauths[$pluginname] = $authtitle;
            }*/
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
            $tmpl = new \core\output\inplace_editable(
                'tool_cleanupusers',
                'authmethod',
                $pluginname,
                has_capability('moodle/site:config', context_system::instance()),
                empty(trim($authvalues))?get_string('all-authmethods', 'tool_cleanupusers'):$authvalues,
                $strkeylist,
                get_string('authmethod_info', 'tool_cleanupusers'),
                get_string('authmethod', 'tool_cleanupusers')
            );
            $attributes = ['multiple' => true];
            $tmpl->set_type_autocomplete($auths, $attributes);
            $authmethod = $OUTPUT->render($tmpl);

            // Time to suspend
            if ($userstatuschecker->needs_suspendtime()) {
                $timetosuspend = $userstatuschecker->get_suspendtime();
                $tmpl = new \core\output\inplace_editable(
                    'tool_cleanupusers',
                    'suspendtime',
                    $pluginname,
                    has_capability('moodle/site:config', context_system::instance()),
                    $timetosuspend,
                    $timetosuspend,
                    $userstatuschecker->get_suspend_hint(),
                    get_string('suspendtime', 'tool_cleanupusers')
                );
                $timetosuspend = $OUTPUT->render($tmpl);
            } else {
                // No suspendtime input
                $timetosuspend = ''; // 'N/A';
            }

            // Time to delete
            $timetodelete = $userstatuschecker->get_deletetime();
            $tmpl = new \core\output\inplace_editable(
                'tool_cleanupusers',
                'deletetime',
                $pluginname,
                has_capability('moodle/site:config', context_system::instance()),
                $timetodelete,
                $timetodelete,
                get_string('deletetime', 'tool_cleanupusers'),
                get_string('deletetime', 'tool_cleanupusers')
            );
            $timetodelete = $OUTPUT->render($tmpl);

            // deleteifneverloggedin
            $neverloggendin = $userstatuschecker->delete_if_never_logged_in_on_suspendtime();
            $keylist = [];
            $keylist[0] = get_string('suspend', 'tool_cleanupusers');
            $keylist[1] = get_string('delete', 'tool_cleanupusers');
            $tmpl = new \core\output\inplace_editable(
                'tool_cleanupusers',
                'neverloggedin',
                $pluginname,
                has_capability('moodle/site:config', context_system::instance()),
                null,
                empty($neverloggendin)?0:$neverloggendin,
                get_string('neverloggedin_info', 'tool_cleanupusers'),
                get_string('neverloggedin_info', 'tool_cleanupusers')
            );
            $tmpl->set_type_select($keylist);
            $neverloggendin = $OUTPUT->render($tmpl);

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
    public function render_index_page($userstoreactivate, $userstosuspend, $usertodelete, $usersneverloggedin, $checker) {
        global $DB;

        $cleanupusers = $DB->get_records('tool_cleanupusers', ['archived' => 1]);

        // Checks if one of the given arrays is empty to prevent rendering empty arrays.
        // If not empty renders the information needed.

        if (empty($userstoreactivate)) {
            $rendertoreactivate = [];
        } else {
            $rendertoreactivate = $this->information_user_reactivate($userstoreactivate, $cleanupusers);
        }
        if (empty($usertodelete)) {
            $rendertodelete = [];
        } else {
            $rendertodelete = $this->information_user_delete($usertodelete, $cleanupusers);
        }
        /*
        if (empty($usersneverloggedin)) {
            $renderneverloggedin = [];
        } else {
            $renderneverloggedin = $this->information_user_notloggedin($usersneverloggedin, $cleanupusers);
        }*/
        if (empty($userstosuspend)) {
            $rendertosuspend = [];
        } else {
            $rendertosuspend = $this->information_user_suspend($userstosuspend, $cleanupusers, $checker);
        }

        // Renders the information for each array in a separate html table.
        $output = '';
        if (!empty($rendertoreactivate)) {
            $url = new \moodle_url('/admin/tool/cleanupusers/archiveusers.php',
                ['action' => archive_filter_form::TO_BE_REACTIVATED, 'checker' => $checker]);

            $output .= $this->render_table_of_users($rendertoreactivate, [
                get_string('willbereactivated', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'),
                get_string('Archived', 'tool_cleanupusers'),
                get_string('authmethod', 'tool_cleanupusers'),
                get_string('Willbe', 'tool_cleanupusers')],
                $checker, $url);
        }
/*        if (!empty($renderneverloggedin)) {
            $output .= $this->render_table_of_users($renderneverloggedin, [get_string('Neverloggedin', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'), get_string('Archived', 'tool_cleanupusers'),
                get_string('Willbe', 'tool_cleanupusers')]);
        }*/
        if (!empty($rendertosuspend)) {
            $url = new \moodle_url('/admin/tool/cleanupusers/toarchive.php',
                ['action' => not_archive_filter_form::TO_BE_ARCHIVED, 'checker' => $checker]);
            $output .= $this->render_table_of_users($rendertosuspend, [
                get_string('willbesuspended', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'),
                get_string('Archived', 'tool_cleanupusers'),
                get_string('authmethod', 'tool_cleanupusers'),
                get_string('Willbe', 'tool_cleanupusers')],
                $checker, $url);
        }
        if (!empty($rendertodelete)) {
            $url = new \moodle_url('/admin/tool/cleanupusers/archiveusers.php',
                ['action' => archive_filter_form::TO_BE_DELETED, 'checker' => $checker]);
            $output .= $this->render_table_of_users($rendertodelete, [
                get_string('willbedeleted', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'),
                get_string('Archived', 'tool_cleanupusers'),
                get_string('authmethod', 'tool_cleanupusers'),
                get_string('Willbe', 'tool_cleanupusers')],
                $checker, $url);
        }

        return $output;
    }

    /**
     * Renders the table for users to suspend.
     * @param array $userstosuspend
     * @return bool|string
     * @throws coding_exception
     */
    public function render_archive_page($userstosuspend) {
        global $CFG, $DB;
        if (empty($userstosuspend)) {
            return "Currently no users will be suspended by the next cronjob";
        } else {
            $idsasstring = '';
            foreach ($userstosuspend as $user) {
                $idsasstring .= $user->id . ',';
            }
            $idsasstring = rtrim($idsasstring, ',');
            $table = new table_sql('tool_deprovisionuser_usertosuspend');
            $table->define_columns(['username', 'lastaccess', 'suspended']);
            $table->define_baseurl($CFG->wwwroot . '/' . $CFG->admin . '/tool/cleanupusers/toarchive.php');
            $table->define_headers([get_string('aresuspended', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'), get_string('Archived', 'tool_cleanupusers')]);
            // TODO Customize the archived status.
            $table->set_sql(
                'username, lastaccess, suspended',
                $DB->get_prefix() . 'tool_cleanupusers_archive',
                'id in (' . $idsasstring . ')'
            );
            $table->setup();
            $tableobject = $table->out(30, true);
            return $tableobject;
        }
    }

    /**
     * Renders the table for users who never logged in.
     * @param array $usersneverloggedin
     * @return bool|string
     * @throws coding_exception
     */
    /*
    public function render_neverloggedin_page($usersneverloggedin) {
        global $DB, $CFG;
        if (empty($usersneverloggedin)) {
            return "Currently no users never logged in.";
        } else {
            $idsasstring = '';
            foreach ($usersneverloggedin as $user) {
                $idsasstring .= $user->id . ',';
            }
            $idsasstring = rtrim($idsasstring, ',');
            $table = new \tool_cleanupusers\neverloggedintable('tool_deprovisionuser_neverloggedin');
            $table->define_baseurl($CFG->wwwroot . '/' . $CFG->admin . '/tool/cleanupusers/neverloggedin.php');
            $table->set_sql('id, username, lastaccess, suspended', $DB->get_prefix() . 'user', 'id in (' . $idsasstring . ')');
            $table->setup();
            $tableobject = $table->out(30, true);
            return $tableobject;
        }
    }
    */
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
     * Formats information for users that are identified by the sub-plugin for deletion.
     * @param array $users array of objects of the user std_class
     * @param array $cleanupusers all users that are currently archived by the plugin.
     * @return array
     */
    private function information_user_delete($users, $cleanupusers) {
        $resultarray = [];
        foreach ($users as $key => $user) {
            $userinformation = [];

            if (!empty($user)) {
                $userinformation = $this->set_user_information_for_table($user, $userinformation, $cleanupusers);
                $userinformation['Willbe'] = get_string('shouldbedelted', 'tool_cleanupusers');
                $url = new moodle_url('/admin/tool/cleanupusers/handleuser.php',
                    ['userid' => $user->id, 'action' => 'delete', 'returnurl' => '/admin/tool/cleanupusers/index.php']);
                $userinformation['link'] = \html_writer::link(
                    $url,
                    '<red>' . $this->output->pix_icon(
                        't/delete',
                        get_string('deleteuser', 'tool_cleanupusers') . '</red>',
                        'moodle',
                        ['class' => "imggroup-" . $user->id]
                    )
                );
            }
            $resultarray[$key] = $userinformation;
        }
        return $resultarray;
    }

    /**
     * Formats information for users that are identified by the sub-plugin for reactivation.
     * @param array $users array of objects of the user std_class
     * @param array $cleanupusers all users that are currently archived by the plugin.
     * @return array
     */
    private function information_user_reactivate($users, $cleanupusers) {
        $resultarray = [];
        foreach ($users as $key => $user) {
            $userinformation = [];

            if (!empty($user)) {
                $userinformation = $this->set_user_information_for_table($user, $userinformation, $cleanupusers);
                $userinformation['Willbe'] = 'Reactivated';

                $url = new moodle_url('/admin/tool/cleanupusers/handleuser.php',
                    ['userid' => $user->id, 'action' => 'reactivate',
                        'returnurl' => '/admin/tool/cleanupusers/index.php']);
                $userinformation['link'] = \html_writer::link(
                    $url,
                    $this->output->pix_icon(
                        't/show',
                        get_string('deleteuser', 'tool_cleanupusers'),
                        'moodle',
                        ['class' => "imggroup-" . $user->id]
                    )
                );
            }
            $resultarray[$key] = $userinformation;
        }
        return $resultarray;
    }

    /**
     * Saves relevant information for users that are identified by the sub-plugin for suspending.
     * @param array $users array of objects of the user std_class
     * @param array $cleanupusers all users that are currently archived by the plugin.
     * @return array
     */
    private function information_user_suspend($users, $cleanupusers, $checker) {
        $result = [];
        foreach ($users as $key => $user) {
            $userinformation = [];
            if (!empty($user)) {
                $userinformation = $this->set_user_information_for_table($user, $userinformation, $cleanupusers);

                $userinformation['Willbe'] = get_string('willbe_archived', 'tool_cleanupusers');
                $url = new moodle_url('/admin/tool/cleanupusers/handleuser.php',
                    ['userid' => $user->id, 'action' => 'suspend', 'checker' => $checker,
                        'returnurl' => '/admin/tool/cleanupusers/index.php']);

                $userinformation['link'] = \html_writer::link(
                    $url,
                    $this->output->pix_icon(
                        't/hide',
                        get_string('hideuser', 'tool_cleanupusers'),
                        'moodle',
                        ['class' => "imggroup-" . $user->id]
                    )
                );
            }
            $result[$key] = $userinformation;
        }
        return $result;
    }

    /**
     * Saves relevant information for users who never logged in.
     * @param array $users array of objects of the user std_class
     * @param array $cleanupusers all users that are currently archived by the plugin.
     * @return array userid as key for user information
     */
    /*
    private function information_user_notloggedin($users, $cleanupusers) {
        $result = [];
        foreach ($users as $key => $user) {
            $userinformation = [];
            if (!empty($user)) {
                $userinformation = $this->set_user_information_for_table($user, $userinformation, $cleanupusers);
                $userinformation['Willbe'] = get_string('nothinghappens', 'tool_cleanupusers');
                $url = new moodle_url('/admin/tool/cleanupusers/handleuser.php', ['userid' => $user->id, 'action' => 'delete']);
                $userinformation['link'] = \html_writer::link(
                    $url,
                    $this->output->pix_icon(
                        't/delete',
                        get_string('deleteuser', 'tool_cleanupusers'),
                        'moodle',
                        ['class' => "imggroup-" . $user->id]
                    )
                );
            }
            $result[$key] = $userinformation;
        }
        return $result;
    }*/

    /**
     * Renders a html-table for an array of users.
     * @param array $users
     * @param array $tableheadings
     * @return string html-table
     */
    private function render_table_of_users($users, $tableheadings, $checker, $url) {
        $table = new html_table();
        $table->head = $tableheadings;
        $table->attributes['class'] = 'generaltable admintable cleanupusers';
        $table->data = [];

        $limit = 15;
        foreach (array_slice($users, 0, $limit, true) as $key => $user) {
            $table->data[$key] = $user;
        }

        if (count($users) > $limit) {
            global $OUTPUT;
            // $url = new \moodle_url('/admin/tool/cleanupusers/toarchive.php', ['checker' => $checker]);
            $link = \html_writer::link(
                $url, '(watch full table)'
            );

            $table->data['last'] = new archiveduser(
                '... ' . $link, // '<i>... truncated!</i>',
                '',
                '',
                '',
                '',
                '',
                ''
            );
        }

        $output = html_writer::table($table);

/*        if (count($users) > $limit) {
            $output .= '<b>Table is truncated!</b><br><br>';
        } */
        return $output;
    }

    /**
     * @param mixed $user
     * @param array $userinformation
     * @param array $cleanupusers
     * @return array
     * @throws coding_exception
     */
    protected function set_user_information_for_table(mixed $user, array $userinformation, array $cleanupusers): array
    {
        $userinformation['username'] = $user->username;
        if ($user->lastaccess > 0)
            $userinformation['lastaccess'] = date('d.m.Y h:i:s', $user->lastaccess);
        else
            $userinformation['lastaccess'] = get_string('neverlogged', 'tool_cleanupusers');

        $isarchivid = array_key_exists($user->id, $cleanupusers);
        if (empty($isarchivid)) {
            $userinformation['archived'] = get_string('No', 'tool_cleanupusers');
        } else {
            $userinformation['archived'] = get_string('Yes', 'tool_cleanupusers');
        }
        $userinformation['auth'] = $user->auth;

        return $userinformation;
    }
}

