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
 * Create an Form Class for the tool_cleanupusers
 *
 * @package   tool_cleanupusers
 * @copyright 2017 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_cleanupusers;
defined('MOODLE_INTERNAL') || die();

use tool_cleanupusers\plugininfo\userstatus;
use tool_cleanupusers\helper;
use moodle_url;

require_once("$CFG->libdir/formslib.php");
require_once(__DIR__ . "/helper.php");

use moodleform;
use core_plugin_manager;

/**
 * Form Class which allows the sideadmin to select between the available sub-plugins.
 *
 * @package   tool_cleanupusers
 * @copyright 2024 Ostfalia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class archive_filter_form extends moodleform {

    const TO_BE_REACTIVATED = 1;
    const TO_BE_DELETED = 2;
    const ALL_USERS = 3;

    const DEFAULT_ACTION = self::ALL_USERS; // does not require plugin input!

    public function __construct() {
        parent::__construct();
    }

    public function get_default_checker() {
        global $SESSION;
        if (!empty($SESSION->checker)) {
            return $SESSION->checker;
        } else {
            $plugins = userstatus::get_enabled_plugins();
            return reset($plugins);
        }
    }

    public function get_default_action() {
        global $SESSION;
        if (!empty($SESSION->action)) {
            return $SESSION->action;
        } else {
            return self::DEFAULT_ACTION;
        }
    }

    /**
     * Defines the sub-plugin select form.
     */
    public function definition() {
        $mform = $this->_form;
        // Gets all enabled plugins of type userstatus.
        $plugins = helper::get_enabled_checkers_with_displayname();
        if (count($plugins) == 0) {
            \core\notification::warning("Note: no userstatus plugin enabled");
        }

        $pluginslinks = [];
        foreach ($plugins as $plugin => $name) {
            global $PAGE;
            $url = new moodle_url($PAGE->url, ['action' => $this->get_default_action(),
                'checker' => $plugin]);
            $pluginslinks[$url->out(false)] = $name;
        }

        $actions = [];
        if (count($plugins) > 0) {
            // Only add chedker actions if there is a chedker enabled.
            $actions[self::TO_BE_REACTIVATED] = get_string('users-to-be-reactivated', 'tool_cleanupusers');
            $actions[self::TO_BE_DELETED] = get_string('users-to-be-deleted', 'tool_cleanupusers');
        }
        $actions[self::ALL_USERS] = get_string('all-archived-users', 'tool_cleanupusers');
        $actionlinks = [];
        $selectedvalue = null;
        foreach ($actions as $action => $name) {
            global $PAGE;
            $url = new moodle_url($PAGE->url, ['action' => $action,
                'checker' => $this->get_default_checker()]);
            // if ($action == $this->get_default_action()) {
                // If selectedvalue is set properly the UI not ok!
                // $selectedvalue = $url->out(false);
            // }
            $actionlinks[$url->out(false)] = $name;
        }

        $selectmenu1 = new \core\output\select_menu('actiontype', $actionlinks, $selectedvalue);
        $selectmenu1->set_label($actions[$this->get_default_action()]);
        global $OUTPUT;
        $actionsselector = \html_writer::tag(
                'h2',
                $OUTPUT->render_from_template('core/tertiary_navigation_selector',
                        $selectmenu1->export_for_template($OUTPUT))
        );
        $mform->addElement('html', $actionsselector);

        if ($this->get_default_action() != self::ALL_USERS) {
            $selectmenu2 = new \core\output\select_menu('checkertype', $pluginslinks /*,
                $this->get_default_checker()*/);
            $selectmenu2->set_label( 'by \''. $plugins[$this->get_default_checker()] . '\'');
            global $OUTPUT;
            $options = \html_writer::tag(
                'h2',
                $OUTPUT->render_from_template('core/tertiary_navigation_selector',
                    $selectmenu2->export_for_template($OUTPUT))
            );
            $mform->addElement('html', $options);
        }
    }

    /**
     * Checks data for correctness
     * Returns a string in an array when the sub-plugin is not available.
     *
     * @param array $data
     * @param array $files
     * @return bool|array array in case the sub-plugin is not valid, otherwise true.
     */
    public function validation($data, $files) {
        switch ($data['action']) {
            case self::ALL_USERS:
                return true;
            case self::TO_BE_REACTIVATED:
            case self::TO_BE_DELETED:
                $plugins = \tool_cleanupusers\plugininfo\userstatus::get_enabled_plugins();
                $issubplugin = array_key_exists($data['subplugin'], $plugins);
                if (!$issubplugin) {
                    return ['subplugin' => get_string('errormessagesubplugin', 'tool_cleanupusers')];
                }
                return $issubplugin;
            default:
                break;
        }
        return ['action' => get_string('errormessageaction', 'tool_cleanupusers')];
    }
}
