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
 * Page to manage archived users
 *
 * @package    tool_cleanupusers
 * @copyright  2024 Ostfalia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cleanupusers\archive_filter_form;
use tool_cleanupusers\archiveuser_filtering;
use tool_cleanupusers\helper;

global $CFG, $PAGE, $OUTPUT;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/table/archive_table.php');

require_once($CFG->dirroot . '/user/filters/lib.php');

// Get URL parameters.
$action  = optional_param('action', null, PARAM_INT);
$checker = optional_param('checker', null, PARAM_ALPHANUMEXT);

$PAGE->set_context(context_system::instance());
// Check permissions.
require_admin();

admin_externalpage_setup('cleanupusers');

$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/archiveusers.php'));

$renderer = $PAGE->get_renderer('tool_cleanupusers');

$content = '';
echo $OUTPUT->header();


$userfilter = new archiveuser_filtering(true, $action, $checker);
echo '<cleanup-user-form>';
$userfilter->display();
[$sqlfilter, $paramfilter] = $userfilter->get_full_sql_filter();
echo '</cleanup-user-form>';

$returnurl = new moodle_url('/admin/tool/cleanupusers/archiveusers.php',
    ['action' => $userfilter->get_action(), 'checker' => $userfilter->get_checker()]);

switch ($userfilter->get_action()) {
    case archive_filter_form::TO_BE_REACTIVATED:
        $checker = $userfilter->get_checker();
        // Update page URL
        $PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/archiveusers.php'),
            ['action' => $userfilter->get_action(), 'checker' => $checker]);

        $subpluginname = "\\userstatus_" . $checker . "\\" . $checker;
        if (!class_exists($subpluginname)) {
            core\notification::warning($subpluginname . ' does not exist');
        } else {
            $userstatuschecker = new $subpluginname();
            try {
                $arrayreactivate = $userstatuschecker->get_to_reactivate();
                if ($sqlfilter != null && $sqlfilter != '') {
                    $sqlfilter .= ' AND ' . helper::users_to_sql_filter($arrayreactivate, 'a');
                } else {
                    $sqlfilter = helper::users_to_sql_filter($arrayreactivate, 'a');
                }
                // var_dump($sqlfilter);
                $archivetable = new \tool_cleanupusers\table\archive_table('tool_cleanupusers_toarchive_table',
                    $sqlfilter, $paramfilter, "reactivate", [], $returnurl, $checker);
            } catch (Exception $e) {
                core\notification::warning($checker . ': ' . $e->getMessage());
            }
        }
        break;
    case archive_filter_form::TO_BE_DELETED:
        $checker = $userfilter->get_checker();
        // Update page URL
        $PAGE->set_url(new moodle_url('/admin/tool/cleanupusers/archiveusers.php'),
            ['action' => $userfilter->get_action(), 'checker' => $checker]);
        $subpluginname = "\\userstatus_" . $checker . "\\" . $checker;
        if (!class_exists($subpluginname)) {
            core\notification::warning($subpluginname . ' does not exist');
        } else {
            $userstatuschecker = new $subpluginname();
            try {
                $users = $userstatuschecker->get_to_delete();
                if ($sqlfilter != null && $sqlfilter != '') {
                    $sqlfilter .= ' AND ' . helper::users_to_sql_filter($users, 'a');
                } else {
                    $sqlfilter = helper::users_to_sql_filter($users, 'a');
                }
                // var_dump($sqlfilter);
                $archivetable = new \tool_cleanupusers\table\archive_table('tool_cleanupusers_todelete_table',
                    $sqlfilter, $paramfilter, "delete", [], $returnurl, $checker);
            } catch (Exception $e) {
                core\notification::warning($checker . ': ' . $e->getMessage());
            }
        }
        break;
    case archive_filter_form::ALL_USERS:
        // only user filter will be applied
        $archivetable = new \tool_cleanupusers\table\archive_table('tool_cleanupusers_toarchive_table',
            $sqlfilter, $paramfilter, "reactivate", [], $returnurl, $checker);

        break;
    default:
        throw new coding_exception('invalid action');
}

$archivetable->define_baseurl($PAGE->url);
$archivetable->out(20, false);

echo $content;
echo $OUTPUT->footer();
