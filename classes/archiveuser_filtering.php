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
 * Filter form for filter user data and checker
 *
 * @package    tool_cleanupusers
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers;

class archiveuser_filtering extends \user_filtering
{
    protected $checkerform;

    public function __construct($baseurl = null) {
        global $SESSION;
        parent::__construct(null, $baseurl);
        $this->checkerform = new \tool_cleanupusers\subplugin_select_form();
        if ($formdata = $this->checkerform->get_data()) {
            $arraydata = get_object_vars($formdata);
            if ($this->checkerform->is_validated()) {
                $SESSION->checker = $arraydata['subplugin'];
            }
        } else {
            if (isset($SESSION->checker)) {
                $default_values = [];
                $default_values['checker'] = $SESSION->checker;
                $this->checkerform->set_data($default_values);
            }
        }
    }

    public function display() {
        $this->checkerform->display();
        parent::display_add();
        parent::display_active();
    }

    public function get_checker() {
        global $SESSION;
        if (!empty($SESSION->checker)) {
            return $SESSION->checker;
        }
        return null;
    }

    public function get_sql_filter($extra='', array $params=null) {
/*        global $SESSION;
        $extra = [];
        $params = [];
        if (!empty($SESSION->checker)) {
            $extra[] = 'checker = :checker';
            $params['checker'] = $SESSION->checker;
            debugging("get_sql_filter => checker data available");
        } */
        return parent::get_sql_filter($extra, $params);
    }


}