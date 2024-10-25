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
use tool_cleanupusers\helper;

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
            $arraytodelete = $userstatuschecker->get_to_delete();
            $deleteresult = helper::change_user_deprovisionstatus($arraytodelete, 'delete', $subplugin);
            $unabletodelete = $deleteresult['failures'];
            $userdeleted = $deleteresult['countersuccess'];

            // Admin is informed about the cron-job and the amount of users that are affected.

            $admin = get_admin();
            // Number of users suspended or deleted.
            $messagetext =
                "\r\n" . get_string('e-mail-deleted', 'tool_cleanupusers', $userdeleted);

            // No Problems occurred during the cron-job.
            if (empty($unabletoactivate) && empty($unabletoarchive) && empty($unabletodelete)) {
                $messagetext .= "\r\n\r\n" . get_string('e-mail-noproblem', 'tool_cleanupusers');
            } else {
                // Extra information for problematic users.
                $messagetext .= "\r\n\r\n" . get_string(
                    'e-mail-problematic_delete',
                    'tool_cleanupusers',
                    count($unabletodelete)
                );
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
}
