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

use moodle_url;
use tool_cleanupusers\plugininfo\userstatus;
use tool_cleanupusers\helper;

require_once("$CFG->libdir/formslib.php");
require_once(__DIR__ . "/helper.php");

use moodleform;
use core_plugin_manager;

/**
 * Form Class which allows the sideadmin to select between the available sub-plugins.
 *
 * @package   tool_cleanupusers
 * @copyright 2017 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class not_archive_filter_form extends moodleform {
    
    // const MANUALLY_SUSPENDED = 1;
    const TO_BE_ARCHIVED = 2;

    const DEFAULT_ACTION = self::TO_BE_ARCHIVED; // does not require plugin!

    // private $checker;

    public function __construct($checker = '') {
        //$this->checker = $this->get_default_checker();
        parent::__construct();
    }

    public function get_default_checker() {
        // debugging("get_default_checker");
        /*if (isset($this->checker)) {
            // debugging("$this->checker " . $this->checker);
            return $this->checker;
        }*/
        global $SESSION;
        if (!empty($SESSION->checker)) {
            // debugging("$SESSION->checker " . $SESSION->checker);
            return $SESSION->checker;
        } else {
            $plugins = userstatus::get_enabled_plugins();
            // debugging("userstatus::get_enabled_plugins " . reset($plugins));
            return reset($plugins);
        }

        // $plugins = userstatus::get_enabled_plugins();
        // return reset($plugins);
    }

    /**
     * Defines the sub-plugin select form.
     */
    public function definition() {
        // debugging("definition: " . $this->get_default_checker() . ' ' . $this->checker);
        $mform = $this->_form;
        // Gets all enabled plugins of type userstatus.
        $plugins = \tool_cleanupusers\helper::get_enabled_checkers_with_displayname();

        $pluginslinks = [];
        foreach ($plugins as $plugin => $name) {
            global $PAGE;
            $url = new moodle_url($PAGE->url, ['action' => self::TO_BE_ARCHIVED, 'checker' => $plugin]);
            $pluginslinks[$url->out(false)] = $name;
        }
        $selectmenu = new \core\output\select_menu('checkertype', $pluginslinks, $this->get_default_checker());
        $selectmenu->set_label(get_string('users-to-be-archived-by', 'tool_cleanupusers') . ' \''.
            $plugins[$this->get_default_checker()] . '\'');
        global $OUTPUT;
        $options = \html_writer::tag(
            'h2',
            $OUTPUT->render_from_template('core/tertiary_navigation_selector',
                $selectmenu->export_for_template($OUTPUT))
        );
        $mform->addElement('html', $options);
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
        $plugins = \tool_cleanupusers\plugininfo\userstatus::get_enabled_plugins();
        if (key_exists('subplugin', $data)) {
            $plugin = $data['subplugin'];
        } else {
            global $SESSION;
            $plugin = $SESSION->checker;
        }
        $issubplugin = array_key_exists($plugin, $plugins);
        if (!$issubplugin) {
            return ['subplugin' => get_string('errormessagesubplugin', 'tool_cleanupusers')];
        }
        return $issubplugin;
    }
}
