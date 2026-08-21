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
 * Migration task for encoding all profile fields for all archived users.
 * @package    tool_cleanupusers
 * @copyright  2026 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers\task;

use core\task\adhoc_task;
use tool_cleanupusers\archiveduser;

/**
 * A class for a migration task for tool_cleanupusers cron.
 *
 * @package    tool_cleanupusers
 * @copyright  2026 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class migrate_profile_fields_task extends adhoc_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('migrate_profile_fields_task', 'tool_cleanupusers');
    }

    /**
     * Migration task for encoding all profile fields for all archived users
     * @return true
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function execute() {
        mtrace("Start encoding profile fields");

        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $records = $DB->get_records('tool_cleanupusers_archive');
        try {
            foreach ($records as $record) {
                archiveduser::encode_fields($record->id);
            }
            $transaction->allow_commit();
        } catch(\Exception $e) {
            mtrace("Encoding profile fields failed: " . $e->getMessage());
            $transaction->rollback($e);
            throw $e;
        }

        mtrace("Encoding profile fields");
        return true;
    }
}
