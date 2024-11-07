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

// Needed for the default plugin.
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
class archive_user_task extends scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('archive_user_task', 'tool_cleanupusers');
    }

    /**
     * Runs the cron job - Calls for the currently activated sub-plugin to return arrays of users.
     * Distinguishes between users to reactivate, suspend and delete.
     * Subsequently, sends an e-mail to the admin containing information about the amount of successfully changed users
     * and the amount of failures.
     * Last but not least triggers an event with the same information.
     *
     * @return true
     */
    public function execute() {
        list($unabletoarchive, $userarchived, $archievdusers, $unabletoactivate, $useractivated) =
            helper::archive_users();

        // Admin is informed about the cron-job and the amount of users that are affected.
        $admin = get_admin();
        // Number of users suspended or deleted.
        $messagetext = get_string('e-mail-archived', 'tool_cleanupusers', $userarchived) .
            "\r\n" . get_string('e-mail-activated', 'tool_cleanupusers', $useractivated);

        // No Problems occured during the cron-job.
        if (empty($unabletoactivate) && empty($unabletoarchive)) {
            $messagetext .= "\r\n\r\n" . get_string('e-mail-noproblem', 'tool_cleanupusers');
        } else {
            // Extra information for problematic users.
            $messagetext .= "\r\n\r\n" . get_string(
                    'e-mail-problematic_suspend',
                    'tool_cleanupusers',
                    count($unabletoarchive)
                ) . "\r\n\r\n" . get_string(
                    'e-mail-problematic_reactivate',
                    'tool_cleanupusers',
                    count($unabletoactivate)
                );
        }

        // Email is send from the do not reply user.
        $sender = \core_user::get_noreply_user();
        email_to_user($admin, $sender, 'Update Infos Cron Job tool_cleanupusers', $messagetext);

        // Triggers deprovisionusercronjob_completed event.
        $context = \context_system::instance();
        $event = deprovisionusercronjob_completed::create_simple($context, $userarchived, []);
        $event->trigger();

        // Log users archived in the last task
        $this->write_csv($archievdusers);

        return true;
    }

    /**
     * Write users to csv file
     *
     * @param $users
     * @return void
     * @throws \dml_exception
     */
    private function write_csv($users) {
        if (empty($users)) {
            return;
        }

        $baseconfig = get_config('tool_cleanupusers');
        if (empty($baseconfig->log_folder)) {
            return;
        }

        $path = $baseconfig->log_folder;
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $out = fopen($path.'/archived_users_'.date("d_m_Y").'.csv', 'w');

        fputcsv($out, array_keys($users[0]), ';');
        foreach ($users as $line) {
            fputcsv($out, $line, ';');
        }

        fclose($out);
    }
}
