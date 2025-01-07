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
 * This file contains language strings used in the lastloginchecker sub-plugin.
 *
 * @package userstatus_lastloginchecker
 * @copyright 2016 N Herrmann
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Last Login Checker';
$string['condition'] = 'User has not logged in for a long time';
$string['suspendtime'] = 'Number of days without log in until a user is suspended';
$string['suspendteachers'] = 'Also suspend teachers';
$string['suspendteachers_info'] = 'If not set, all users who are NOT enrolled in ANY course as a teacher will be suspended. In other words, anyone who is registered as a teacher anywhere will not be suspended. Otherwise (if set), teachers are also suspended.';

$string['keeproles'] = 'Do not suspend users with roles';
$string['keeproles_info'] = 'Users who are enrolled with the selected roles will not be suspended by last login checker.<br>
Note that the roles must be valid in the course context.';
