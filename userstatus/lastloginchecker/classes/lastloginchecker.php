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
 * Sub-plugin lastloginchecker.
 *
 * @package   userstatus_lastloginchecker
 * @copyright 2016/17 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace userstatus_lastloginchecker;

use tool_cleanupusers\userstatuschecker;

/**
 * Class that checks the status of different users depending on the time they did not signed in.
 *
 * @package    userstatus_lastloginchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lastloginchecker extends userstatuschecker {
    /**
     * constructor
     */
    public function __construct() {
        parent::__construct(self::class);
    }

    public function condition_suspend_sql(): array {
        // Attribute 'lastaccess' has a default value of 0, so this must be checked.
        return [
            " lastaccess != 0 AND lastaccess < :timelimit" ,
            [ 'timelimit'  => time() - $this->get_suspendtime_in_sec() ]
        ];
    }

    public function condition_reactivate_sql($tca, $tc): array {
        return [
            "{$tca}.lastaccess >= :timelimit" ,
            [ 'timelimit'  => time() - $this->get_suspendtime_in_sec() ]
        ];
    }

    public function shall_suspend($user): bool {
        // Get all courses the user is enrolled into
        $courses = enrol_get_all_users_courses($user->id, true, "id");
        // Get all teacher roles
        $roleids = array_keys(get_archetype_roles('teacher'));

        foreach ($courses as $course) {
            foreach($roleids as $roleid) {
                // Get all users who are enrolled in course as teacher
                $users = get_role_users($roleid, \context_course::instance($course->id));
                foreach($users as $courseparticipant) {
                    // check if user belongs to teachers
                    // (seems to be more complicated then needed)
                    if ($courseparticipant->id == $user->id) {
                        return false;
                    }

                }

            }

//            if (is_enrolled(\context_course::instance($course->id), $user->id)) {

//            }
        }
        return true;
    }
}
