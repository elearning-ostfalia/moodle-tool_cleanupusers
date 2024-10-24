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
 * A scheduled task for tool_cleanupusers cron.
 *
 * The Class archive_user_task is supposed to show the admin a page of users which will be archived and expects a submit or
 * cancel reaction.
 * @package    tool_cleanupusers
 * @copyright  2016 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers\task;

use tool_cleanupusers\cleanupusers_exception;
// Needed for the default plugin.
use tool_cleanupusers\archiveduser;
use tool_cleanupusers\event\deprovisionusercronjob_completed;
use core\task\scheduled_task;
/**
 * A class for a scheduled task for tool_cleanupusers cron.
 *
 * @package    tool_cleanupusers
 * @copyright  2016 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_user_task extends scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('delete_user_task', 'tool_cleanupusers');
    }

    /**
     * Runs the cron job - Calls for the currently activated sub-plugin to return arrays of users.
     * Distinguishes between users to reactivate and suspend.
     * Subsequently, sends an e-mail to the admin containing information about the amount
     * of successfully changed users and the amount of failures.
     * Last but not least triggers an event with the same information.
     *
     * @return true
     */
    public function execute() {
        // wrong order!
        // $pluginsenabled =  \core_plugin_manager::instance()->get_enabled_plugins("userstatus");
        // correct order:
        $pluginsenabled = \tool_cleanupusers\plugininfo\userstatus::get_enabled_plugins();
        if (!$pluginsenabled) {
            // Nothing to be done.
            return true;
        }

        foreach ($pluginsenabled as $subplugin => $dir) {

            $mysubpluginname = "\\userstatus_" . $subplugin . "\\" . $subplugin;
            $userstatuschecker = new $mysubpluginname();

            // Private function is executed to suspend, delete and activate users.
            $archivearray = [];
            $reactivatearray = $userstatuschecker->get_to_reactivate();

            $suspendresult = [];

/*            $result = $this->change_user_deprovisionstatus($reactivatearray, 'reactivate', $subplugin);
            $unabletoactivate = $result['failures'];
            $useractivated = $result['countersuccess'];
*/

            $arraytodelete = $userstatuschecker->get_to_delete();

            // do not delete users who must be reactivated (but do not reactivate them here)
            $arraytodelete = array_diff_key($arraytodelete, $reactivatearray);

            $deleteresult = $this->change_user_deprovisionstatus($arraytodelete, 'delete', $subplugin);
            $unabletodelete = $deleteresult['failures'];
            $userdeleted = $deleteresult['countersuccess'];



            // Admin is informed about the cron-job and the amount of users that are affected.

            $admin = get_admin();
            // Number of users suspended or deleted.
            $messagetext =
                "\r\n" . get_string('e-mail-deleted', 'tool_cleanupusers', $userdeleted)/* .
                "\r\n" . get_string('e-mail-activated', 'tool_cleanupusers', $useractivated)*/;

            // No Problems occured during the cron-job.
            if (empty($unabletoactivate) && empty($unabletoarchive) && empty($unabletodelete)) {
                $messagetext .= "\r\n\r\n" . get_string('e-mail-noproblem', 'tool_cleanupusers');
            } else {
                // Extra information for problematic users.
                $messagetext .= "\r\n\r\n" . get_string(
                    'e-mail-problematic_delete',
                    'tool_cleanupusers',
                    count($unabletodelete)
                ) /*. "\r\n\r\n" . get_string(
                    'e-mail-problematic_reactivate',
                    'tool_cleanupusers',
                    count($unabletoactivate)
                )*/;
            }

            // Email is send from the do not reply user.
            $sender = \core_user::get_noreply_user();
            email_to_user($admin, $sender, 'Update Infos Cron Job tool_cleanupusers', $messagetext);

            // Triggers deprovisionusercronjob_completed event.
            $context = \context_system::instance();
            $event = deprovisionusercronjob_completed::create_simple($context, [], $userdeleted);
            $event->trigger();
        }

        return true;
    }

    /**
     * Deletes, suspends or reactivates an array of users.
     *
     * @param  array $userarray of users
     * @param  string $intention of suspend, delete, reactivate
     * @return array ['numbersuccess'] successfully changed users ['failures'] userids, who could not be changed.
     * @throws \coding_exception
     */
    private function change_user_deprovisionstatus($userarray, $intention, $checker) {
        // Checks whether the intention is valid.
/*        if (!in_array($intention, ['suspend', 'reactivate', 'delete'])) {
            throw new \coding_exception('Invalid parameters in tool_cleanupusers.');
        }
*/
        // Number of successfully changed users.
        $countersuccess = 0;

        // Array of users who could not be changed.
        $failures = [];

        // Alternatively one could have written different function for each intention.
        // However, this would have produced duplicated code.
        // Therefore, checking the intention parameter repeatedly was preferred.
        foreach ($userarray as $key => $user) {
            if ($user->deleted == 0 && !is_siteadmin($user) && !isguestuser($user)) {
                $changinguser = new archiveduser(
                    $user->id,
                    $user->suspended,
                    $user->lastaccess,
                    $user->username,
                    $user->deleted,
                    $user->auth,
                    $checker
                );
                try {
                    $changinguser->delete_me();
/*                    switch ($intention) {
                        case 'suspend':
                            $changinguser->archive_me($checker);
                            break;
                        case 'reactivate':
                            $changinguser->activate_me();
                            break;
                        case 'delete':
                            break;
                        // No default since if-clause checks the intention parameter.
                    }*/
                    $countersuccess++;
                } catch (\Throwable $e) {
                    $failures[$key] = $user->id;
                }
            }
        }
        $result = [];
        $result['countersuccess'] = $countersuccess;
        $result['failures'] = $failures;
        return $result;
    }
}
