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
 * * @copyright  2024 Ostfalia
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

    public function __construct() {
        parent::__construct(self::class);
    }

    public function shall_suspend($user) : bool {
        $courses = enrol_get_all_users_courses($user->id, true, "startdate, enddate, visible");

        foreach ($courses as $course) {
            if ($course->visible && $course->enddate > time()) {
                debugging("valid course");
                return false;
            }
        }
        return true;
        // todo: check is course is active or not

        // var_dump($courses);
        // return count($courses) == 0;
    }

    public function shall_reactivate($user) : bool {
        return !$this->shall_suspend($user);
    }

    /** does not use suspend time value */
    public function needs_suspendtime() : bool {
        return false;
    }

}

