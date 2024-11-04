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
//require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/dataformatlib.php');

$dataformat = optional_param('dataformat', '', PARAM_ALPHA);


// Check permissions.
require_admin();

list($unabletoarchive, $userarchived, $archievdusers, $unabletoactivate, $useractivated) = \tool_cleanupusers\helper::archive_users(true);

$rows = new ArrayObject([]);
foreach ($archievdusers as $row) {
    $rows->append($row);
}


$fields = array_keys($archievdusers[0]);

\core\dataformat::download_data('archive_preview', $dataformat, $fields, $rows->getIterator(), function(array $row) use ($dataformat) {
    // HTML export content will need escaping.
    if (strcasecmp($dataformat, 'html') === 0) {
        $row = array_map(function($cell) {
            return s($cell);
        }, $row);
    }

    return $row;
});
