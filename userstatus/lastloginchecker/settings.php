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
 * Settings.php
 * @package   userstatus_lastloginchecker
 * @copyright 2016/17 N Herrmann/2024 Ostfalia
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Included in admin/tool/cleanupusers/classes/plugininfo/userstatus.php, therefore, need to include global variables.
global $CFG, $PAGE;

require_once($CFG->libdir . '/adminlib.php');

if ($hassiteconfig) {
    $settings->add(new admin_setting_configcheckbox('userstatus_lastloginchecker/keepteachers',
            get_string('keepteachers', 'userstatus_lastloginchecker'),
            get_string('keepteachers_info', 'userstatus_lastloginchecker'),
            1));

    $settings->add(new admin_setting_configtext('userstatus_lastloginchecker/suspendtimeteacher',
            get_string('suspendtime_teacher', 'userstatus_lastloginchecker'),
            get_string('suspendtime_teacher_info', 'userstatus_lastloginchecker'),
            '200',
            PARAM_INT
    ));

    $settings->hide_if('userstatus_lastloginchecker/suspendtimeteacher',
            'userstatus_lastloginchecker/keepteachers', 'eq', '1');
}