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
 * Sub-plugin never login checker.
 *
 * @package   userstatus_neverloginchecker
 * @copyright 2016/17 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace userstatus_neverloginchecker;

use tool_cleanupusers\archiveduser;
use tool_cleanupusers\userstatusinterface;
use tool_cleanupusers\userstatuschecker;

/**
 * Class that checks the status of different users depending on the time they did not signed in.
 *
 * @package    userstatus_neverloginchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class neverloginchecker extends userstatuschecker {
    /**
     * This constructor sets timesuspend and timedelete from days to seconds.
     */
    public function __construct() {
        parent::__construct(self::class);
    }

    public function condition_suspend_sql() : array {
        return [" lastaccess = 0 and timecreated < :timelimit" ,
            [ 'timelimit'  => time() - $this->get_suspendtime_in_sec() ]];
    }

    /**
     * need to be handled because the timelimit might have changed in meantime
     * @param $tca
     * @param $tc
     * @return array
     */
    public function condition_reactivate_sql($tca, $tc) : array {
        return ["({$tca}.lastaccess != 0 or {$tca}.timecreated >= :timelimit)" ,
            [ 'timelimit'  => time() - $this->get_suspendtime_in_sec() ]];
    }

}
