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

require_once("$CFG->libdir/formslib.php");
use moodleform;
use core_plugin_manager;

/**
 * Form Class which allows the sideadmin to select between the available sub-plugins.
 *
 * @package   tool_cleanupusers
 * @copyright 2017 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class subplugin_select_form extends moodleform {

    private $withall;

    public function __construct($withall = true)
    {
        $this->withall = $withall;
        parent::__construct();
    }

    /**
     * Defines the sub-plugin select form.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        // Gets all enabled plugins of type userstatus.
        $plugins = core_plugin_manager::instance()->get_enabled_plugins("userstatus");
        if (count($plugins) == 0) {
            \core\notification::warning(get_string('errormessagenoplugin', 'tool_cleanupusers'));
        }
        if ($this->withall) {
            $plugins[''] = '[all]';
        } else {
            return; // no plugin available => do form
        }
/*
        $types = [];

        foreach ($plugins as $value) {
            $types[$value] = $value;
        }
        $isnopluginselected = empty(get_config('tool_cleanupusers'));
        // Different text in case no plugin was selected beforehand.
        if ($isnopluginselected) {
            $text = 'Please select a subplugin';
        } else {
            $text = 'Change the subplugin';
        }
*/
        $mform->addElement('select', 'subplugin', 'Please select a subplugin', $plugins);
        if ($this->withall) {
            $mform->setDefault('subplugin', '[all]');
        } else {
            $mform->setDefault('subplugin', $plugins[0]);
        }

        $context = [
            'trigger1' => 'id_subplugin'
        ];

        // Render invisible submit button
        global $OUTPUT;
        $mform->addElement('html', $OUTPUT->render_from_template('tool_cleanupusers/filterform', $context));
        //        }
        // $mform->addElement('submit', 'reset', 'Submit');

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
        $plugins = core_plugin_manager::instance()->get_enabled_plugins("userstatus");
        $plugins[''] = '[all]';
        // debugging($data['subplugin']);

        $issubplugin = false;
        foreach ($plugins as $key => $value) {
            if ($key == $data['subplugin']) {
                $issubplugin = true;
                break;
            }
        }
        if ($issubplugin == false) {
            return ['subplugin' => get_string('errormessagesubplugin', 'tool_cleanupusers')];
        }
        return $issubplugin;
    }
}
