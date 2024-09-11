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
 * Create a custom sql_table for the tool_cleanupusers
 *
 * @package   tool_cleanupusers
 * @copyright 2019 Justus Dieckmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers\table;

use core_user\fields;

/**
 * Create a class for a custom sql_table for the tool_cleanupusers
 *
 * @package   tool_cleanupusers
 * @copyright 2019 Justus Dieckmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_table extends \table_sql {
    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param array $users
     * @param String $sqlwhere
     * @param array $param
     */
    public function __construct($uniqueid, $users, $sqlwhere, $param, $intention) {
        parent::__construct($uniqueid);

        // Define the list of columns to show.
        $columns = ['id', 'username', 'fullname', 'lastaccess', 'auth'];
        $this->define_columns($columns);

        // debugging($intention);
        // Define the titles of columns to show in header.
        switch ($intention) { // todo make enum or something like that.
            case 'suspend':
                $header = get_string('willbesuspended', 'tool_cleanupusers');
                break;
            case 'delete':
                $header = get_string('willbedeleted', 'tool_cleanupusers');
                break;
            default:
                debugging($intention);
                $header = get_string('Neverloggedin', 'tool_cleanupusers');
                break;
        }

        $headers = [get_string('id', 'tool_cleanupusers'), $header,
            get_string('fullname'), get_string('lastaccess', 'tool_cleanupusers'),
            get_string('authmethod', 'tool_cleanupusers')];
        $this->define_headers($headers);

        $idsasstring = '';
        foreach ($users as $user) {
            $idsasstring .= $user->id . ',';
        }
        $idsasstring = rtrim($idsasstring, ',');
        $where = 'id IN (' . $idsasstring . ')';
        if ($sqlwhere != null && $sqlwhere != '') {
            $where .= ' AND ' . $sqlwhere;
        }
        $this->set_sql('id, username, lastaccess, auth, ' . implode(', ', fields::get_name_fields()), '{user}', $where, $param);
    }

    public function col_lastaccess($row) {
        debugging('col_lastaccess');
        if ($row->lastaccess > 0)
            return date('d.m.Y h:i:s', $row->lastaccess);
        else
            return get_string('neverlogged', 'tool_cleanupusers');
    }
}
