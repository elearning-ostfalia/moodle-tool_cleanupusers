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
 * This file contains language strings used in the cleanupusers admin tool.
 *
 * @package tool_cleanupusers
 * @copyright 2016 N Herrmann
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Clean up users';
$string['pluginsettingstitle'] = 'General settings';
$string['subplugintype_userstatus'] = 'Returns the status of users';
$string['subplugintype_userstatus_plural'] = 'Returns the status of users';
$string['neverloggedin'] = 'Manage users who never logged in';
$string['neverloggedininfo'] = 'List of "valid" users who have not yet logged in and who can be archived manually.';
$string['toarchivelink'] = 'Users to be archived';
$string['toarchiveby'] = 'Users who will be archived by \'{$a}\'';
$string['toarchive'] = 'Users to be archived';
$string['reactivate'] = 'Archived users';
$string['reactivateuser'] = 'Reactivate this user';
$string['achivedusers'] = 'Archived users';
$string['todelete2'] = 'Users to be deleted';
$string['todelete'] = 'Manage users to be deleted';
$string['lastaccess'] = 'Last access';
$string['usersarchived'] = 'User \'{$a}\' has been archived';
$string['Yes'] = 'Yes';
$string['id'] = 'ID';
$string['No'] = 'No';
$string['Archived'] = 'Archived';
$string['Willbe'] = 'Will be';
$string['Neverloggedin'] = 'Users that never logged in';
$string['willbereactivated'] = 'Users who will be reactivated';
$string['willbesuspended'] = 'Users who will be suspended resp. archived';
$string['willbedeleted'] = 'Users who will be deleted';
$string['usersdeleted'] = 'User \'{$a}\' has been deleted.';
$string['usersreactivated'] = 'User \'{$a}\' has been reactivated.';
$string['showuser'] = 'Activate User';
$string['hideuser'] = 'Suspend and archive user account';
$string['archiveuser'] = 'Archive user account';
$string['deleteuser'] = 'Delete User';
$string['aresuspended'] = 'Users currently suspended';
$string['archive_user_task'] = 'Archive Users';
$string['delete_user_task'] = 'Delete Users';
$string['willbe_archived'] = 'archived in the next cron-job';
$string['pluginsettingstitle'] = 'General settings';
$string['sett_suspendfirstname'] = 'Firstname for suspended';
$string['sett_suspendfirstname_description'] = 'Choose a firstname for suspended users';
$string['sett_suspendusername'] = 'Username for suspended';
$string['sett_suspendusername_description'] = 'Choose a username for suspended users (must be lowercase)';
$string['sett_suspendlastname'] = 'Lastname for suspended';
$string['sett_suspendlastname_description'] = 'Choose a lastname for suspended users';
$string['sett_title'] = 'Suspended user settings';
$string['suspendfirstname'] = 'Anonym';
$string['suspendusername'] = 'anonym';
$string['shouldbedelted'] = 'deleted in the next cron-job';
$string['neverlogged'] = 'Never logged in';
$string['nothinghappens'] = 'Not handled since the user never logged in';
$string['waittodelete'] = 'The user is suspended and will not be deleted in the next cron-job.';
$string['e-mail-archived'] = 'In the last cron-job {$a} users were archived.';
$string['e-mail-deleted'] = 'In the last cron-job {$a} users were deleted.';
$string['e-mail-activated'] = 'In the last cron-job {$a} users were reactivated.';
$string['errormessagenotactive'] = 'Not able to activate user';
$string['errormessagenotdelete'] = 'Not able to delete user';
$string['errormessagenotsuspend'] = 'Not able to suspend user';
$string['errormessagenoaction'] = 'The requested action could not be executed.';
$string['errormessagesubplugin'] = 'The sub-plugin you selected is not available. The default will be used.';
$string['errormessageaction'] = 'The action you selected is not available. The default will be used.';
$string['errormessagenoplugin'] = 'There is no sub-plugin enabled.';
$string['e-mail-problematic_delete'] = 'In the last cron-job {$a} users caused exception and could not be deleted.';
$string['e-mail-problematic_suspend'] = 'In the last cron-job {$a} users caused exception and could not be suspended.';
$string['e-mail-problematic_reactivate'] = 'In the last cron-job {$a} users caused exception and could not be reactivated.';
$string['e-mail-noproblem'] = 'No problems occurred in plugin tool_cleanupusers in the last run.';
$string['cronjobcomplete'] = 'tool_cleanupusers cron job complete';
$string['cronjobwasrunning'] = 'The tool_cleanupusers cron job was running. No user was suspended or deleted.';
$string['using-plugin'] = 'You are currently using the <b>{$a}</b> Plugin.<br>';
$string['actpluginshdr'] = 'Available subplugins';
$string['authmethod'] = 'Authentication method';
$string['authmethod_info'] = 'Only users with this authentication method are handled by this plugin. Leave empty if all users shall be handled.';
$string['all-authmethods'] = '(all)';
$string['condition'] = 'Condition';
$string['suspendtime'] = 'Time until suspension [days]';
$string['deletetime'] = 'Time until deletion after suspension [days]';
$string['suspend'] = 'archive';
$string['delete'] = 'delete immediately';
$string['deleteifneverloggedin'] = 'Suspend action if user was never logged in';
$string['neverloggedin_info'] = 'What shall be done if a user is to be suspended and has never logged in?';
$string['pendingactions'] = 'Pending cleanup actions';
$string['timestamp'] = 'Archive timestamp';
$string['warn_reactivate'] = 'Please note that reactivated users will probably be archived again the next time cron is run.<br>
To prevent this, you can disable the corresponding checker or change the user\'s authentication method (or stop the task).';
$string['users-to-be-archived-by'] = 'Users to be archived by';
$string['users-to-be-archived'] = 'Users to be archived';
$string['users-to-be-deleted'] = 'Users to be deleted';
$string['users-to-be-reactivated'] = 'Users to be reactivated';
$string['all-archived-users'] = 'All archived users';
$string['confirm-delete'] = 'Do you really want to delete user \'<b>{$a->firstname} {$a->lastname}</b>\' and related data.<br>
All user data will be finally and completely deleted and cannot be restored later!';
$string['confirm-delete-title'] = 'Completely delete user';



