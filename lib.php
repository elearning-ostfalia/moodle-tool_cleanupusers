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

use core_external\external_api;

function tool_cleanupusers_inplace_editable($itemtype, $plugin, $newvalue1) {
    global $DB;

    if ($itemtype === 'authmethod') {
        // Must call validate_context for either system, or course or course module context.
        // This will both check access and set current context.
        external_api::validate_context(context_system::instance());

        // Check permission of the user to update this item.
        require_login();
        require_capability('moodle/site:config', context_system::instance());

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
                // $newvalue = "is_string";
            } else {
                $newvaluetext = '';
                // $newvalue = "is_other";
            }
        }

        set_config('auth_method', $newvaluetext, 'userstatus_' . $plugin);

        $templ = new \core\output\inplace_editable(
            'tool_cleanupusers',
            'authmethod',
            $plugin,
            has_capability('moodle/site:config', context_system::instance()),
            $newvaluetext,
            $newvalue1,
            'Type authentication method', // new lang_string('editmytestnamefield', 'tool_mytest'),
            'Authent. method', // new lang_string('newvaluestring', 'tool_mytest', format_string($record->name))
        );
        $attributes = ['multiple' => true];
        $templ->set_type_autocomplete($auths, $attributes);
        return $templ;
    }

    if ($itemtype === 'deletetime') {
        // Must call validate_context for either system, or course or course module context.
        // This will both check access and set current context.
        external_api::validate_context(context_system::instance());

        // Check permission of the user to update this item.
        require_login();
        require_capability('moodle/site:config', context_system::instance());

        // Clean input and update configuration.
        $newvalue1 = clean_param($newvalue1, PARAM_NOTAGS);

        set_config('deletetime', $newvalue1, 'userstatus_' . $plugin);

        $templ = new \core\output\inplace_editable(
            'tool_cleanupusers',
            'authmethod',
            $plugin,
            has_capability('moodle/site:config', context_system::instance()),
            $newvalue1,
            $newvalue1,
            get_string('deletetime', 'tool_cleanupusers'),
            get_string('deletetime', 'tool_cleanupusers')
        );
        return $templ;
    }

    if ($itemtype === 'suspendtime') {
        // Must call validate_context for either system, or course or course module context.
        // This will both check access and set current context.
        external_api::validate_context(context_system::instance());

        // Check permission of the user to update this item.
        require_login();
        require_capability('moodle/site:config', context_system::instance());

        // Clean input and update configuration.
        $newvalue1 = clean_param($newvalue1, PARAM_NOTAGS);

        set_config('suspendtime', $newvalue1, 'userstatus_' . $plugin);

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

}
