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
 * This file contains language strings used in the nocoursechecker sub-plugin.
 *
 * @package userstatus_nocoursechecker
 * @copyright 2024 Ostfalia
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'No active course Checker';
$string['condition'] = 'User is not enrolled in an active course';
// $string['suspendtime'] = 'Time since user is created [days]';
$string['keepteachers'] = 'Do not suspend teachers';
$string['keepteachers_info'] = 'Do not suspend users who are enrolled as a teacher in one of her or his courses.';
$string['waitingperiod'] = 'Waiting period after registration [days]';
$string['waitingperiod_info'] = 'Time that is waited after the registration of a user until 
the check is carried out for the first time if there is no course enrollment.
Should be equal to neverlogin suspend period. 
This period is needed to ensure that users are not immediately suspended 
when they are registered.';


