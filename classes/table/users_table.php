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
    private $checker;
    private $returnurl;
    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param array $users
     * @param String $sqlwhere
     * @param array $param
     */
    public function __construct($uniqueid, $sqlwhere, $param, $checker, $returnurl) {
        parent::__construct($uniqueid);
        $this->checker = $checker;
        $this->returnurl = $returnurl;

        // Define the list of columns to show.
        $columns = ['id', 'username', 'fullname', 'lastaccess', 'timecreated',  'auth', 'suspend'];
        $this->define_columns($columns);
        $this->set_attribute('id', 'users_' . $checker);

        // Define the titles of columns to show in header.
        // $header = get_string('willbesuspended', 'tool_cleanupusers');

        $headers = [
            get_string('id', 'tool_cleanupusers'),
            get_string('username'), // $header,
            get_string('fullname'),
            get_string('lastaccess', 'tool_cleanupusers'),
            get_string('timecreated', 'tool_cleanupusers'),
            get_string('authmethod', 'tool_cleanupusers'),
            ''];
        $this->define_headers($headers);
        $this->set_sql('id, username, lastaccess, auth, suspended, timecreated, ' .
            implode(', ', fields::get_name_fields()), '{user}', $sqlwhere, $param);
    }

    public function col_lastaccess($user) {
        if ($user->lastaccess > 0) {
            return $this->display_usersuspended($user, date('d.m.Y h:i:s', $user->lastaccess));
        } else {
            return $this->display_usersuspended($user, get_string('never'));
        }
    }

    public function col_timecreated($user) {
        if (!empty($user->timecreated)) {
            return $this->display_usersuspended($user, date('d.m.Y h:i:s', $user->timecreated));
        }
        return '??';
    }

    private function display_usersuspended($user, $value) {
        if ($user->suspended) {
            return \html_writer::tag('span', $value, array('class'=>'usersuspended'));
        }
        return $value;
    }

    public function col_suspend($user) {
        $url = new \moodle_url('/admin/tool/cleanupusers/handleuser.php', [
            'userid' => $user->id,
            'sesskey' => sesskey(),
            'action' => 'suspend', 'checker' => $this->checker,
            'returnurl' => $this->returnurl,
        ]);
        global $OUTPUT;
        if ($user->suspended) {
            // User is already suspened, so he or she only needs to be archived
            return
                $OUTPUT->pix_icon(
                    't/show',
                    get_string('archiveuser', 'tool_cleanupusers'),
                    'moodle',
                    ['class' => "imggroup-" . $user->id]
                ) . '/ ' .
                \html_writer::link(
                $url,
                $OUTPUT->pix_icon(
                    'e/save',
                    get_string('archiveuser', 'tool_cleanupusers'),
                    'moodle',
                    ['class' => "imggroup-" . $user->id]
                )
            );
        }
        return \html_writer::link(
            $url,
            $OUTPUT->pix_icon(
                't/hide',
                get_string('hideuser', 'tool_cleanupusers'),
                'moodle',
                ['class' => "imggroup-" . $user->id]
            ) . '/ ' .
            $OUTPUT->pix_icon(
                'e/save',
                get_string('hideuser', 'tool_cleanupusers'),
                'moodle',
                ['class' => "imggroup-" . $user->id]
            )
        );
    }

    public function col_id($user) {
        return $this->display_usersuspended($user, $user->id);
    }

    public function col_username($user) {
        return $this->display_usersuspended($user, $user->username);
    }

    public function col_auth($user) {
        return $this->display_usersuspended($user, $user->auth);
    }

    public function col_timestamp($user) {
        return $this->display_usersuspended($user, date('d.m.Y h:i:s', $user->timestamp));
    }

    public function col_fullname($user) {
        if ($user->suspended) {
            return $this->display_usersuspended($user, parent::col_fullname($user));
//            return $this->display_usersuspended($user, fullname($user));
        }
        return parent::col_fullname($user);
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
