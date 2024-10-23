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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/classes/output/inplace_editable.php');
require_once(__DIR__ . '/classes/userstatuschecker.php');

use core_external\external_api;

function tool_cleanupusers_inplace_editable($itemtype, $plugin, $newvalue1) {
    if ($itemtype === 'authmethod') {
        // Must call validate_context for either system, or course or course module context.
        // This will both check access and set current context.
        external_api::validate_context(context_system::instance());

        // Check permission of the user to update this item.
        require_admin();

        // Clean input and update the record.
        $newvalue1 = clean_param($newvalue1, PARAM_NOTAGS);

        // Prepare the element for the output:
        $auths = get_enabled_auth_plugins();

        // convert string with array text e.g. "['0','1']" to actual array
        $newvaluearray = str_replace("'", '"', $newvalue1);
        $newvaluearray = json_decode($newvaluearray, true);

        if (is_array($newvaluearray)) {
            $displayvalues = [];
            foreach ($newvaluearray as $index ) {
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
        // Must call validate_context for either system, or course or course module context.
        // This will both check access and set current context.
        external_api::validate_context(context_system::instance());

        // Check permission of the user to update this item.
        require_admin();

        // Clean input and update configuration.
        $newvalue1 = clean_param($newvalue1, PARAM_INT);
        if ($newvalue1 < 0 ) {
            // ignore values < 0
            $newvalue1 = get_config(CONFIG_DELETETIME, 'userstatus_' . $plugin);
            throw new moodle_exception("value must not be negative");
        }
        set_config(CONFIG_DELETETIME, $newvalue1, 'userstatus_' . $plugin);

        $templ = \tool_cleanupusers\helper::render_deletetime_editable($plugin, $newvalue1);
        return $templ;
    }

    if ($itemtype === 'suspendtime') {
        // Must call validate_context for either system, or course or course module context.
        // This will both check access and set current context.
        external_api::validate_context(context_system::instance());

        // Check permission of the user to update this item.
        require_admin();

        // Clean input and update configuration.
        $newvalue1 = clean_param($newvalue1, PARAM_INT);

        set_config(CONFIG_SUSPENDTIME, $newvalue1, 'userstatus_' . $plugin);

        $templ = new \core\output\inplace_editable(
            'tool_cleanupusers',
            'authmethod',
            $plugin,
            has_capability('moodle/site:config', context_system::instance()),
            $newvalue1,
            $newvalue1,
            get_string('suspendtime', 'tool_cleanupusers'),
            get_string('suspendtime', 'tool_cleanupusers')
        );
        return $templ;
    }

    if ($itemtype === 'neverloggedin') {
        // Must call validate_context for either system, or course or course module context.
        // This will both check access and set current context.
        external_api::validate_context(context_system::instance());

        // Check permission of the user to update this item.
        require_admin();

        // Clean input and update the record.
        $newvalue1 = clean_param($newvalue1, PARAM_NOTAGS);

        // Prepare the element for the output:
        $keylist = [];
        $keylist[0] = get_string('suspend', 'tool_cleanupusers');
        $keylist[1] = get_string('delete', 'tool_cleanupusers');

        set_config(CONFIG_NEVER_LOGGED_IN, $newvalue1, 'userstatus_' . $plugin);

        $templ = new \core\output\inplace_editable(
            'tool_cleanupusers',
            'neverloggedin',
            $plugin,
            has_capability('moodle/site:config', context_system::instance()),
            null,
            empty($newvalue1)?0:$newvalue1,
            get_string('neverloggedin_info', 'tool_cleanupusers'),
            get_string('neverloggedin_info', 'tool_cleanupusers')
        );
        $templ->set_type_select($keylist);
        return $templ;
    }


}



