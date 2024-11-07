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

global $CFG;
require_once($CFG->dirroot . '/user/filters/lib.php');

require_once(__DIR__ . '/not_archive_filter_form.php');
require_once(__DIR__ . '/archive_filter_form.php');

/**
 * filter form for tables
 *
 * @package   tool_cleanupusers
 * @copyright 2024 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archiveuser_filtering extends \user_filtering {

    /**
     * filter form
     * @var archive_filter_form|not_archive_filter_form
     */
    protected $checkerform;

    public function __construct($archive, $urlaction, $urlchecker) {
        global $SESSION;
        parent::__construct();

        // if (isset($urlaction) || (isset($SESSION->archive) && $SESSION->archive != $archive)) {
        if (isset($SESSION->archive) && $SESSION->archive != $archive) {
            // Invalidate session variables in case of switching form or
            // in case of a redirect
            unset($SESSION->checker);
            unset($SESSION->action);
        }
        $SESSION->archive = $archive;

        if (isset($urlaction)) {
            // set default values from URL

            $SESSION->checker = $urlchecker;
            $SESSION->action = $urlaction;
        }

        if ($archive) {
            $this->checkerform = new archive_filter_form();
        } else {
            $this->checkerform = new not_archive_filter_form();
        }

        if ($formdata = $this->checkerform->get_data()) {
            $arraydata = get_object_vars($formdata);
            if ($this->checkerform->is_validated()) {
                if (isset($arraydata['subplugin'])) {
                    $SESSION->checker = $arraydata['subplugin'];
                }
                if (isset($arraydata['action'])) {
                    $SESSION->action = $arraydata['action'];
                }
            }
        } else {
            if (isset($urlaction)) {
                // set default values from URL
                // debugging("set_data " . $urlaction . ' ' . $urlchecker);
                $this->checkerform->set_data(['action' => $urlaction, 'subplugin' => $urlchecker]);
                $SESSION->checker = $urlchecker;
                $SESSION->action = $urlaction;
            } else {
                if (isset($SESSION->checker)) {
                    // set default values from session
                    $defaultvalues = [];
                    $defaultvalues['subplugin'] = $SESSION->checker;
                    // $defaultvalues['checker'] = $SESSION->checker;
                    if (isset($SESSION->action)) {
                        $defaultvalues['action'] = $SESSION->action;
                    }
                    // debugging("setdata from SESSION");
                    // var_dump($defaultvalues);
                    $this->checkerform->set_data($defaultvalues);
                }
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
        if (empty($SESSION->checker)) {
            $SESSION->checker = $this->checkerform->get_default_checker();
        }
        return $SESSION->checker;
    }

    public function get_action() {
        global $SESSION;
        if (empty($SESSION->action)) {
            // Return default action which does not require a plugin to be selected.
            // debugging("default action");
            $SESSION->action = $this->checkerform::DEFAULT_ACTION;
        }
        return $SESSION->action;
    }

    public function get_full_sql_filter($withchecker = false) {
        $extra = '';
        $params = null;
        if ($withchecker) {
            global $SESSION;
            if (!empty($SESSION->checker)) {
                $params = [];
                $extra = 'checker = :checker';
                $params['checker'] = $SESSION->checker;
                // debugging("get_sql_filter => checker data available");
            }
        }
        return parent::get_sql_filter($extra, $params);
    }
}
