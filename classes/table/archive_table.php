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
 *
 * Create a custom sql_table for the tool_cleanupusers
 *
 * @package   tool_cleanupusers
 * @copyright 2019 Justus Dieckmann, Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers\table;

require_once($CFG->libdir . '/tablelib.php');

use core_user\fields;

/**
 * Create a class for a custom sql_table for the tool_cleanupusers
 *
 * @package   tool_cleanupusers
 * @copyright 2019 Justus Dieckmann, Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archive_table extends \table_sql {
    private $returnurl;
    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param array $users
     * @param String $sqlwhere
     * @param array $param
     */
    public function __construct($uniqueid, $sqlwhere, $param, $intention, $sql, $returnurl, $checker) {
        parent::__construct($uniqueid);

        $this->returnurl = $returnurl;
        // Define the list of columns to show.
        $columns = [
            'id'         => get_string('id', 'tool_cleanupusers'),
            'username'   => get_string('username'),
            'fullname'   => get_string('fullname'),
            'lastaccess' => get_string('lastaccess', 'tool_cleanupusers'),
            'timecreated' => get_string('timecreated', 'tool_cleanupusers'),
            'auth'       => get_string('authmethod', 'tool_cleanupusers'),
            'checker'    => 'checker',
            'timestamp'  => get_string('timestamp', 'tool_cleanupusers'),
            $intention   => '',
        ];

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));
        $this->set_attribute('id', 'archive_' . $checker . '_' . $intention);

        if ($sqlwhere != null && $sqlwhere != '') {
            $where = $sqlwhere;
        } else {
            $where = '1=1';
        }

        // read all users from archive table
        if (count($sql) > 0) {
            $this->set_sql($sql['fields'],
                    $sql['from'], $sql['where'] . ' and ' . $where, $param);
        } else {
            $fields = 'a.id, a.username, a.lastaccess, a.timecreated, a.auth, a.firstname, a.lastname, c.checker, c.timestamp, '.
                implode(', ', fields::get_name_fields());
            $this->set_sql($fields,
                '{tool_cleanupusers_archive} a JOIN {tool_cleanupusers} c ON c.id = a.id',
                $where, $param);
        }
    }

    private function display_usersuspended($value) {
        return \html_writer::tag('span', $value, array('class'=>'usersuspended'));
    }
    public function col_fullname($user) {
        return $this->display_usersuspended(fullname($user));
    }

    public function col_reactivate($user) {
        global $OUTPUT;
        $url = new \moodle_url('/admin/tool/cleanupusers/handleuser.php', [
            'sesskey' => sesskey(),
            'userid' => $user->id,
            'action' => 'reactivate',
            'returnurl' => $this->returnurl,
        ]);
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
        $url = new \moodle_url('/admin/tool/cleanupusers/handleuser.php', [
            'sesskey' => sesskey(),
            'userid' => $user->id,
            'action' => 'delete',
            'checker' => $user->checker,
            'returnurl' => $this->returnurl,
        ]);

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

    public function col_id($user) {
        return $this->display_usersuspended($user->id);
    }

    public function col_username($user) {
        return $this->display_usersuspended($user->username);
    }

    public function col_auth($user) {
        return $this->display_usersuspended($user->auth);
    }

    public function col_timestamp($user) {
        return $this->display_usersuspended(date('d.m.Y h:i:s', $user->timestamp));
    }

    public function col_timecreated($user) {
        return $this->display_usersuspended(date('d.m.Y h:i:s', $user->timecreated));
    }

    public function col_lastaccess($user) {
        if ($user->lastaccess > 0) {
            return $this->display_usersuspended(date('d.m.Y h:i:s', $user->lastaccess));
        } else {
            return $this->display_usersuspended(get_string('never'));
        }
    }

    /**
     * Get the table content.
     */
    public function get_content($limit): string {
        ob_start();
        $this->out($limit, false);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
