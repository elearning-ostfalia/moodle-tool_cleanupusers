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
 * Sub-plugin nocoursechecker.
 *
 * @package    userstatus_nocoursechecker
 * @copyright  2024 Ostfalia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace userstatus_nocoursechecker;

use tool_cleanupusers\archiveduser;
use tool_cleanupusers\userstatusinterface;
use tool_cleanupusers\userstatuschecker;

/**
 * Class that checks if the user is enrolled in an active course
 *
 * @package    userstatus_nocoursechecker
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nocoursechecker extends userstatuschecker {

    protected $waitingperiod;
    public function __construct() {
        parent::__construct(self::class);
        if (!get_config('userstatus_nocoursechecker', 'keepteachers')) {
            $this->waitingperiod = get_config('userstatus_nocoursechecker', 'waitingperiod');
            if ($this->waitingperiod < 0) {
                // no valid configuration value
                debugging('negative configuration value for \'waitingperiod\'');
                $this->waitingperiod = 0;
            }
        }
    }

    public function shall_suspend($user): bool {
        // Read all courses that the user is enrolled into with ACTIVE enrolment
        $courses = enrol_get_all_users_courses($user->id, true, "startdate, enddate, visible");
        if (get_config('userstatus_nocoursechecker', 'keepteachers')) {
            // do not suspend the teacher
            if (userstatuschecker::is_teacher($user, $courses)) {
                return false;
            }
        } else {
            if (count($courses) == 0) {
                // User is registered in Moodle but does not yet have a course yet
                // cannot determine if user is a teacher since he or she is not enrolled anywhere
                if ($user->timecreated + ($this->waitingperiod * DAYSECS) > time()) {
                    return false;
                }
            }
        }

        // student handling
        foreach ($courses as $course) {
            if (!$course->visible) {
                // invisible courses are not active
                continue;
            }
            if (!isset($course->enddate)) {
                // missing enddate means that the course has not been ended
                // => course is still active
                return false;
            }
            assert(isset($course->enddate));
            assert(isset($course->visible));

            if ($course->enddate == '0') {
                // Special case enddate = '0'
                // => means missing enddate
                // => course is still active
                return false;
            }

            if ($course->enddate > time()) {
                // Enddate in the future
                // => Course is still active
                return false;
            }

            if ($course->enddate < time()) {
                // Enddate is not in the past
                // => course is not active
                continue;
            }

            if (!isset($course->startdate)) {
                debugging('missing course startdate');
                // => ????
            }
        }

        return true;
    }

    public function shall_reactivate($user): bool {
        return !$this->shall_suspend($user);
    }

    /** does not use suspend time value */
    public function needs_suspendtime(): bool {
        return false;
    }

}

