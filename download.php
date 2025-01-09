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
 * Script to download the preview as csv (or other format).
 *
 * @package   tool_cleanupusers
 * @copyright 2024 Ostfalia
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');
// require_once($CFG->libdir . '/dataformatlib.php');

$dataformat = required_param('dataformat', PARAM_ALPHA);
$type = required_param('type', PARAM_ALPHA);


// Check permissions.
require_admin();

switch ($type) {
    case "archive":
        list($unabletoarchive, $userarchived, $downloadusers, $unabletoactivate, $useractivated) =
            \tool_cleanupusers\helper::archive_users(true);
        break;
    case "delete":
        list($unabletodelete, $userdeleted, $downloadusers) =
            \tool_cleanupusers\helper::delete_users(true);
        break;
    default:
        throw new moodle_exception("invalid download call");
}

$rows = new ArrayObject([]);
foreach ($downloadusers as $row) {
    $rows[$row['id']] = $row;
    // $rows->append($row);
}

if (count($rows) == 0) {
    echo get_string('nodownload', 'tool_cleanupusers');
} else {
    $fields = array_keys($downloadusers[0]);

    \core\dataformat::download_data($type . '_preview', $dataformat, $fields, $rows->getIterator(), function(array $row) use ($dataformat) {
        // HTML export content will need escaping.
        if (strcasecmp($dataformat, 'html') === 0) {
            $row = array_map(function($cell) {
                return s($cell);
            }, $row);
        }

        return $row;
    });
}
