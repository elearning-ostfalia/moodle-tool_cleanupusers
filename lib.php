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
 * handler for inplace editabled
 *
 * @package tool_cleanupusers
 * @copyright 2024 Ostfalia
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/classes/output/inplace_editable.php');
require_once(__DIR__ . '/classes/userstatuschecker.php');

use core_external\external_api;

/**
 * updates the configuration with the new value entered with editable
 *
 * @param $itemtype type of inplace editable
 * @param $plugin plugin name
 * @param $newvalue1 new value
 * @return array|\core\output\inplace_editable|void
 * @throws \core_external\restricted_context_exception
 * @throws coding_exception
 * @throws dml_exception
 * @throws invalid_parameter_exception
 * @throws moodle_exception
 */
function tool_cleanupusers_inplace_editable($itemtype, $plugin, $newvalue1) {
    // Must call validate_context for either system, or course or course module context.
    // This will both check access and set current context.
    external_api::validate_context(context_system::instance());

    // Check permission of the user to update this item.
    require_admin();

    if ($itemtype === 'authmethod') {
        $newvalue1 = clean_param($newvalue1, PARAM_NOTAGS);

        // Prepare the element for the output:
        $auths = get_enabled_auth_plugins();

        // convert string with array text e.g. "['0','1']" to actual array
        $newvaluearray = str_replace("'", '"', $newvalue1);
        $newvaluearray = json_decode($newvaluearray, true);

        if (is_array($newvaluearray)) {
            $displayvalues = [];
            foreach ($newvaluearray as $index) {
                $displayvalues[] = $auths[$index];
            }
            $newvaluetext = implode(',', $displayvalues);
        } else {
            if (is_string($newvaluearray)) {
                $newvaluetext = $auths[$newvaluearray];
            } else {
                $newvaluetext = '';
            }
        }

        set_config(CONFIG_AUTH_METHOD, $newvaluetext, 'userstatus_' . $plugin);

        return \tool_cleanupusers\helper::render_auth_editable($plugin, $newvaluetext, $newvalue1);
    }

    if ($itemtype === 'deletetime') {
        $newvalue1 = clean_param($newvalue1, PARAM_INT);
        if ($newvalue1 < 0 ) {
            // ignore values < 0
            throw new moodle_exception("value must not be negative");
        }
        set_config(CONFIG_DELETETIME, $newvalue1, 'userstatus_' . $plugin);
        $templ = \tool_cleanupusers\helper::render_deletetime_editable($plugin, $newvalue1);
        return $templ;
    }

    if ($itemtype === 'suspendtime') {
        $newvalue1 = clean_param($newvalue1, PARAM_INT);
        if ($newvalue1 < 0 ) {
            // ignore values < 0
            throw new moodle_exception("value must not be negative");
        }

        set_config(CONFIG_SUSPENDTIME, $newvalue1, 'userstatus_' . $plugin);
        return \tool_cleanupusers\helper::render_suspendtime_editable(
            $plugin, $newvalue1);
    }

    if ($itemtype === 'neverloggedin') {
        $newvalue1 = clean_param($newvalue1, PARAM_NOTAGS);
        if (!empty($newvalue1) && ($newvalue1 != 0 && $newvalue1 != 1)) {
            throw new moodle_exception("invalid value");
        }

        set_config(CONFIG_NEVER_LOGGED_IN, $newvalue1, 'userstatus_' . $plugin);
        return \tool_cleanupusers\helper::render_no_login_editiable($plugin, $newvalue1);
    }
}



