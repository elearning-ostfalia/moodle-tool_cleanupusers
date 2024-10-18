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
use tool_cleanupusers\tools;

require_once("$CFG->libdir/formslib.php");
require_once(__DIR__ . "/tools.php");

use moodleform;
use core_plugin_manager;

/**
 * Form Class which allows the sideadmin to select between the available sub-plugins.
 *
 * @package   tool_cleanupusers
 * @copyright 2017 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class archive_filter_form extends moodleform {

    const TO_BE_REACTIVATED = 1;
    const TO_BE_DELETED = 2;
    const ALL_USERS = 3;

    const DEFAULT_ACTION = self::ALL_USERS; // does not require plugin!

    public function __construct()
    {
        parent::__construct();
    }

    public function get_default_checker() {
        $plugins = userstatus::get_enabled_plugins();
        return reset($plugins);
    }

    /**
     * Defines the sub-plugin select form.
     */
    public function definition() {
        $mform = $this->_form;
        // Gets all enabled plugins of type userstatus.
        $plugins = tools::get_enabled_checkers_with_displayname();

        $actions = [];
        $actions[self::TO_BE_REACTIVATED] = 'users to be reactivated by';
        $actions[self::TO_BE_DELETED] = 'users to be deleted by';
        $actions[self::ALL_USERS] = 'all archived users';

        $selectline = [];
        $selectline[] = &$mform->createElement('select', 'action', '', $actions);
        $selectline[] = &$mform->createElement('select', 'subplugin', '', $plugins);
        $mform->addGroup($selectline, 'selectline', 'Show', array(' '), false);

        $mform->setDefault('action', self::DEFAULT_ACTION);
        if (count($plugins) == 0) {
            \core\notification::warning(get_string('errormessagenoplugin', 'tool_cleanupusers'));
        } else {
            $mform->setDefault('subplugin', $this->get_default_checker());
        }

        // Add invisible submit button
        $context = [
            'pluginid' => 'id_subplugin',
            'actionid' => 'id_action',
            'hidevalue' => self::ALL_USERS
        ];
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
