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
class reactivate_table extends \table_sql {
    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param array $users
     * @param String $sqlwhere
     * @param array $param
     */
    public function __construct($uniqueid, $sqlwhere, $param, $intention) {
        parent::__construct($uniqueid);

        // Define the list of columns to show.
        $columns = [
            'id'         => get_string('id', 'tool_cleanupusers'),
            'username'   => '',
            'fullname'   => get_string('fullname'),
            'lastaccess' => get_string('lastaccess', 'tool_cleanupusers'),
            'auth'       => get_string('authmethod', 'tool_cleanupusers'),
            'checker'    => 'checker',
            'timestamp'    => get_string('timestamp', 'tool_cleanupusers'),
            $intention   => get_string($intention=='reactivate'?'willbesuspended':'willbedeleted',

                'tool_cleanupusers')
        ];

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));

        if ($sqlwhere != null && $sqlwhere != '') {
            $where = $sqlwhere;
        } else {
            $where = '1=1';
        }

        // read all users from archive table
        $fields = 'a.id, a.username, a.lastaccess, a.auth, a.firstname, a.lastname, c.checker, c.timestamp, '.
            implode(', ', fields::get_name_fields());
        $this->set_sql($fields,
            '{tool_cleanupusers_archive} a JOIN {tool_cleanupusers} c ON c.id = a.id',
            $where, $param);
    }

    public function col_reactivate($user) {
        global $OUTPUT;
        $url = new \moodle_url('/admin/tool/cleanupusers/handleuser.php',
            ['userid' => $user->id, 'action' => 'reactivate']);
        return \html_writer::link(
            $url,
            $OUTPUT->pix_icon(
                't/show',
                get_string('reactivateuser', 'tool_cleanupusers'),
                'moodle',
                ['class' => "imggroup-" . $user->id]
            )
        );
    }

    public function col_delete($user) {
        $url = new \moodle_url('/admin/tool/cleanupusers/handleuser.php',
            ['userid' => $user->id, 'action' => 'delete', 'checker' => $user->checker]);

        global $OUTPUT;
        return \html_writer::link(
            $url,
            $OUTPUT->pix_icon(
                't/delete',
                get_string('deleteuser', 'tool_cleanupusers'),
                'moodle',
                ['class' => "imggroup-" . $user->id]
            )
        );
    }

    public function col_timestamp($user) {
        return date('d.m.Y h:i:s', $user->timestamp);
    }

    public function col_lastaccess($user) {
        if ($user->lastaccess > 0)
            return date('d.m.Y h:i:s', $user->lastaccess);
        else
            return get_string('neverlogged', 'tool_cleanupusers');
    }
}
