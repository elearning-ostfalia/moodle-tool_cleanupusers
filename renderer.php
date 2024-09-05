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

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/tablelib.php');
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
        $txt->authmethod = get_string('authmethod', 'userstatus_timechecker');

        $authsavailable = core_plugin_manager::instance()->get_plugins_of_type('userstatus');
        // var_dump($authsavailable);
        $class = \core_plugin_manager::resolve_plugininfo_class('userstatus');
        $authsenabled = $class::get_enabled_plugins();
        // $authsenabled = core_plugin_manager::instance()->get_enabled_plugins("userstatus");
        if (!$authsenabled) {
            $authsenabled = [];
        }

        // core_component::get_plugin_list('auth');
        /* get_enabled_auth_plugins(true); // fix the list of enabled auths
        if (empty($CFG->auth)) {
            $authsenabled = array();
        } else {
            $authsenabled = explode(',', $CFG->auth);
        }
        */

        // construct the display array, with enabled auth plugins at the top, in order
        $displayauths = array();
        $registrationauths = array();
        $registrationauths[''] = $txt->disable;
        $authplugins = array();
/*        foreach ($authsenabled as $auth) {
            $authplugin = get_auth_plugin($auth);
            $authplugins[$auth] = $authplugin;
            /// Get the auth title (from core or own auth lang files)
            $authtitle = $authplugin->get_title();
            /// Apply titles
            $displayauths[$auth] = $authtitle;
            if ($authplugin->can_signup()) {
                $registrationauths[$auth] = $authtitle;
            }
        }
*/
        foreach ($authsavailable as $auth => $dir) {
            if (array_key_exists($auth, $displayauths)) {
                continue; //already in the list
            }
            $displayauths[$auth] = $dir->displayname;


            // $authplugin = get_auth_plugin($auth);
            // $authplugins[$auth] = $userstatuschecker;
            /// Get the auth title (from core or own auth lang files)
            // $authtitle = $userstatuschecker; // $userstatuschecker->get_title();
            /// Apply titles
            // $authsenabled[$auth] = $auth; // $authtitle;
/*            if ($userstatuschecker->can_signup()) {
                $registrationauths[$auth] = $authtitle;
            }*/
        }


        $return = $OUTPUT->heading(get_string('actpluginshdr', 'tool_cleanupusers'), 3, 'main');
        $return .= $OUTPUT->box_start('generalbox authsui');

        $table = new html_table();
        $table->head  = array($txt->name, $txt->authmethod, $txt->enable, $txt->updown, $txt->settings);
        $table->colclasses = array('leftalign', 'centeralign', 'centeralign', 'centeralign', 'centeralign');
        $table->data  = array();
        $table->attributes['class'] = 'admintable generaltable';
        $table->id = 'manageauthtable';

        //add always enabled plugins first
        /*        $displayname = $displayauths['manual'];
        $settings = "<a href=\"settings.php?section=authsettingmanual\">{$txt->settings}</a>";
        $usercount = $DB->count_records('user', array('auth'=>'manual', 'deleted'=>0));
        $table->data[] = array($displayname, $usercount, '', '', $settings, '', '');
        $displayname = $displayauths['nologin'];
        $usercount = $DB->count_records('user', array('auth'=>'nologin', 'deleted'=>0));
        $table->data[] = array($displayname, $usercount, '', '', '', '', '');
*/

        // iterate through auth plugins and add to the display table
        $updowncount = 1;
        $authcount = count($authsavailable); // count($authsenabled);
        $url = "index.php?sesskey=" . sesskey();
        foreach ($displayauths as $auth => $name) {
            $class = '';
            // var_dump($auth);
            $mysubpluginname = "\\userstatus_" . $auth . "\\" . $auth;
            $userstatuschecker = new $mysubpluginname();

            // hide/show link
            if (in_array($auth, $authsenabled)) {
                $hideshow = "<a href=\"$url&amp;action=disable&amp;userstatus=$auth\">";
                $hideshow .= $OUTPUT->pix_icon('t/hide', get_string('disable')) . '</a>';
                $enabled = true;
                $displayname = $name;
            }
            else {
                $hideshow = "<a href=\"$url&amp;action=enable&amp;userstatus=$auth\">";
                $hideshow .= $OUTPUT->pix_icon('t/show', get_string('enable')) . '</a>';
                $enabled = false;
                $displayname = $name;
                $class = 'dimmed_text';
            }

            // $usercount = $DB->count_records('user', array('auth'=>$auth, 'deleted'=>0));
            $authmethod = $userstatuschecker->get_authentication_method();

            // up/down link (only if auth is enabled)
            $updown = '';
            if ($enabled) {
                if ($updowncount > 1) {
                    $updown .= "<a href=\"$url&amp;action=up&amp;auth=$auth\">";
                    $updown .= $OUTPUT->pix_icon('t/up', get_string('moveup')) . '</a>&nbsp;';
                }
                else {
                    $updown .= $OUTPUT->spacer() . '&nbsp;';
                }
                if ($updowncount < $authcount) {
                    $updown .= "<a href=\"$url&amp;action=down&amp;auth=$auth\">";
                    $updown .= $OUTPUT->pix_icon('t/down', get_string('movedown')) . '</a>&nbsp;';
                }
                else {
                    $updown .= $OUTPUT->spacer() . '&nbsp;';
                }
                ++ $updowncount;
            }

            // settings link
            if (file_exists( __DIR__ . '/userstatus/'.$auth.'/settings.php')) {
                $settings = "<a href=\"{$CFG->wwwroot}/admin/settings.php?section=cleanupusers_userstatus$auth\">{$txt->settings}</a>";
            } else {
                $settings = '';
            }

            // Add a row to the table.
            $row = new html_table_row(array($displayname, $authmethod, $hideshow, $updown, $settings));
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
    public function render_index_page($userstoreactivate, $userstosuspend, $usertodelete, $usersneverloggedin) {
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
        if (empty($usersneverloggedin)) {
            $renderneverloggedin = [];
        } else {
            $renderneverloggedin = $this->information_user_notloggedin($usersneverloggedin, $cleanupusers);
        }
        if (empty($userstosuspend)) {
            $rendertosuspend = [];
        } else {
            $rendertosuspend = $this->information_user_suspend($userstosuspend, $cleanupusers);
        }

        // Renders the information for each array in a separate html table.
        $output = '';
        if (!empty($rendertoreactivate)) {
            $output .= $this->render_table_of_users($rendertoreactivate, [get_string('willbereactivated', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'), get_string('Archived', 'tool_cleanupusers'),
                get_string('Willbe', 'tool_cleanupusers')]);
        }
        if (!empty($renderneverloggedin)) {
            $output .= $this->render_table_of_users($renderneverloggedin, [get_string('Neverloggedin', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'), get_string('Archived', 'tool_cleanupusers'),
                get_string('Willbe', 'tool_cleanupusers')]);
        }
        if (!empty($rendertosuspend)) {
            $output .= $this->render_table_of_users($rendertosuspend, [get_string('willbesuspended', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'),
                get_string('Archived', 'tool_cleanupusers'), get_string('Willbe', 'tool_cleanupusers')]);
        }
        if (!empty($rendertodelete)) {
            $output .= $this->render_table_of_users($rendertodelete, [get_string('willbedeleted', 'tool_cleanupusers'),
                get_string('lastaccess', 'tool_cleanupusers'),
                get_string('Archived', 'tool_cleanupusers'), get_string('Willbe', 'tool_cleanupusers')]);
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
    /**
     * Functions returns the heading for the tool_cleanupusers.
     *
     * @return string
     */
    public function get_heading() {
        $output = '';
        $output .= $this->heading(get_string('pluginname', 'tool_cleanupusers'));
        return $output;
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
                $userinformation['username'] = $user->username;
                $userinformation['lastaccess'] = date('d.m.Y h:i:s', $user->lastaccess);

                $isarchivid = array_key_exists($user->id, $cleanupusers);
                if (empty($isarchivid)) {
                    $userinformation['archived'] = get_string('No', 'tool_cleanupusers');
                } else {
                    $userinformation['archived'] = get_string('Yes', 'tool_cleanupusers');
                }
                $userinformation['Willbe'] = get_string('shouldbedelted', 'tool_cleanupusers');
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
                $userinformation['username'] = $user->username;
                $userinformation['lastaccess'] = date('d.m.Y h:i:s', $user->lastaccess);
                $isarchivid = array_key_exists($user->id, $cleanupusers);
                if (empty($isarchivid)) {
                    $userinformation['archived'] = get_string('No', 'tool_cleanupusers');
                } else {
                    $userinformation['archived'] = get_string('Yes', 'tool_cleanupusers');
                }
                $userinformation['Willbe'] = 'Reactivated';
                $url = new moodle_url('/admin/tool/cleanupusers/handleuser.php', ['userid' => $user->id, 'action' => 'reactivate']);
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
    private function information_user_suspend($users, $cleanupusers) {
        $result = [];
        foreach ($users as $key => $user) {
            $userinformation = [];
            if (!empty($user)) {
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

                $userinformation['Willbe'] = get_string('willbe_archived', 'tool_cleanupusers');

                $url = new moodle_url('/admin/tool/cleanupusers/handleuser.php', ['userid' => $user->id, 'action' => 'suspend']);

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
    private function information_user_notloggedin($users, $cleanupusers) {
        $result = [];
        foreach ($users as $key => $user) {
            $userinformation = [];
            if (!empty($user)) {
                $userinformation['username'] = $user->username;
                $userinformation['lastaccess'] = get_string('neverlogged', 'tool_cleanupusers');
                $isarchivid = array_key_exists($user->id, $cleanupusers);
                if (empty($isarchivid)) {
                    $userinformation['archived'] = get_string('No', 'tool_cleanupusers');
                } else {
                    $userinformation['archived'] = get_string('Yes', 'tool_cleanupusers');
                }
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
    }

    /**
     * Renders a html-table for an array of users.
     * @param array $users
     * @param array $tableheadings
     * @return string html-table
     */
    private function render_table_of_users($users, $tableheadings) {
        $table = new html_table();
        $table->head = $tableheadings;
        $table->attributes['class'] = 'generaltable admintable cleanupusers';
        $table->data = [];
        foreach ($users as $key => $user) {
            $table->data[$key] = $user;
        }
        $htmltable = html_writer::table($table);
        return $htmltable;
    }
}

