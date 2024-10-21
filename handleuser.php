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
 * Suspend, delete or reactivate user. This is called when sideadmin changes user from the cleanupusers
 *
 * administration page.
 *
 * @package tool_cleanupusers
 * @copyright 2016 N Herrmann
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_login();

global $CFG, $DB, $PAGE, $USER;
require_once($CFG->dirroot . '/user/lib.php');

$userid     = required_param('userid', PARAM_INT);
// One of: suspend, reactivate or delete.
$action     = required_param('action', PARAM_TEXT);
$returnurl  = required_param('returnurl', PARAM_URL);
$confirm    = optional_param('confirm', false, PARAM_BOOL);


$PAGE->set_url('/admin/tool/cleanupusers/handleuser.php');
$PAGE->set_context(context_system::instance());

$user = $DB->get_record('user', ['id' => $userid]);
require_capability('moodle/user:update', $PAGE->context);
require_admin();

$url = $returnurl; // new moodle_url('/admin/tool/cleanupusers/index.php');

switch ($action) {
    // User should be suspended.
    case 'suspend':
        // Sideadmins, the current $USER and user who are already suspended can not be handeled.
        if (!is_siteadmin($user) && $user->suspended != 1 && $USER->id != $userid) {
            $checker        = required_param('checker', PARAM_TEXT);
            $deprovisionuser = new \tool_cleanupusers\archiveduser(
                $userid,
                $user->suspended,
                $user->lastaccess,
                $user->username,
                $user->deleted,
                $user->auth,
                $checker
            );
            try {
                $deprovisionuser->archive_me($checker);
            } catch (\tool_cleanupusers\cleanupusers_exception $e) {
                // Notice user could not be suspended.
                notice(get_string('errormessagenoaction', 'tool_cleanupusers'), $url);
            }
            // User was successfully suspended.
            notice(get_string('usersarchived', 'tool_cleanupusers', $user->username), $url);
        } else {
            // Notice user could not be suspended.
            notice(get_string('errormessagenotsuspend', 'tool_cleanupusers'), $url);
        }
        break;
    // User should be reactivated.
    case 'reactivate':
        if (!is_siteadmin($user) && $user->suspended != 0 && $USER->id != $userid) {
            $deprovisionuser = new \tool_cleanupusers\archiveduser(
                $userid,
                $user->suspended,
                $user->lastaccess,
                $user->username,
                $user->deleted,
                $user->auth,
                ''
            );
            try {
                $deprovisionuser->activate_me();
                $user = $DB->get_record('user', ['id' => $userid],
                    '*', MUST_EXIST);
            } catch (\tool_cleanupusers\cleanupusers_exception $e) {
                // Notice user could not be reactivated.
                notice(get_string('errormessagenoaction', 'tool_cleanupusers'), $url);
            }
            // User successfully reactivated.
            notice(get_string('usersreactivated', 'tool_cleanupusers', $user->username), $url);
        } else {
            // Notice user could not be reactivated.
            notice(get_string('errormessagenotactive', 'tool_cleanupusers'), $url);
        }
        break;
    // User should be deleted.
    case 'delete':
        if ($confirm && confirm_sesskey()) {
            if (!is_siteadmin($user) && $user->deleted != 1 && $USER->id != $userid) {
                // debugging('delete ');
                // exit();
                $archiveuser = $DB->get_record('tool_cleanupusers_archive', ['id' => $userid],
                    '*', MUST_EXIST);
                $deprovisionuser = new \tool_cleanupusers\archiveduser(
                    $userid,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    ''
                );
                try {
                    $deprovisionuser->delete_me();
                } catch (\tool_cleanupusers\cleanupusers_exception $e) {
                    $url = new moodle_url('/admin/tool/cleanupusers/index.php');
                    // Notice user could not be deleted.
                    notice(get_string('errormessagenoaction', 'tool_cleanupusers'), $url);
                }
                notice(get_string('usersdeleted', 'tool_cleanupusers', $archiveuser->username), $url);
            } else {
                // Notice user could not be deleted.
                notice(get_string('errormessagenoaction', 'tool_cleanupusers'), $url);
            }
        } else {
            $yesurl = new moodle_url($PAGE->url, [
                'confirm'=>1, 'sesskey'=>sesskey(), 'userid' => $userid, 'action' => $action, 'returnurl' => $returnurl
            ]);
            $archiveuser = $DB->get_record('tool_cleanupusers_archive', ['id' => $userid],
                '*', MUST_EXIST);
            $message = get_string('confirm-delete', 'tool_cleanupusers',
                $archiveuser);

            $title = get_string('confirm-delete-title', 'tool_cleanupusers');
            $PAGE->set_title($title);
            $PAGE->navbar->add($title);
// $PAGE->navbar->add($fullname);

            global $OUTPUT;
            echo $OUTPUT->header();
// echo $OUTPUT->heading($fullname);
            $displayoptions = ['confirmtitle' => $title];
            $confirmbutton = new single_button(
                $yesurl,
                get_string('delete'),
                'post',
                single_button::BUTTON_DANGER
            );
            $cancelbutton = new single_button(new moodle_url($returnurl), get_string('cancel'));

            echo $OUTPUT->confirm($message, $confirmbutton, $cancelbutton, $displayoptions);
//            echo $OUTPUT->confirm($message, $yesurl, $returnurl, $displayoptions);
        }

        break;
    // Action is not valid.
    default:
        notice(get_string('errormessagenoaction', 'tool_cleanupusers'), $url);
        break;
}
exit();
