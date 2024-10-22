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
 * Sub-plugin suspendedchecker.
 *
 * @package   userstatus_suspendedchecker
 * @copyright 2024 Ostfalia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace userstatus_suspendedchecker;

use tool_cleanupusers\userstatuschecker;

/**
 * Class that checks the for users who are suspended manually.
 *
 * @package    userstatus_suspendedchecker
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suspendedchecker extends userstatuschecker {
    /**
     * constructor
     */
    public function __construct() {
        parent::__construct(self::class);
    }

    /**
     * check that suspended flag is set and
     * the user is not already archived. If he or she is archived then the
     * suspended flag will also be set!
     * @return array
     */
    public function condition_suspend_sql() : array {
        return [" suspended = 1", []];
    }

    /**
     * Users cannot be reactivated by external circumstances
     * @param $tca
     * @param $tc
     * @return array
     */
    public function condition_reactivate_sql($tca, $tc) : array {
        return ["false" , []];
    }

    /**
     * SQL condition should result in an empty set.
     * @param $user
     * @return bool
     */
    public function shall_reactivate($user) : bool {
        throw new \coding_exception('unreachable code reached');
        // return false;
    }

    public function get_to_reactivate() {
        return [];
    }

    /**
     * As the time since a user is suspended cannot actually determined
     * there is no suspendtime
     * @return bool
     */
    public function needs_suspendtime() : bool {
        return false;
    }
}
