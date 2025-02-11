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
 * Class archive user.
 *
 * @package   tool_cleanupusers
 * @copyright 2017 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_cleanupusers;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');

use core\session\manager;
/**
 * The class collects the necessary information to suspend, delete and activate users.
 *
 * It can be used in sub-plugins, since the constructor assures that all necessary information is transferred.
 *
 * @package   tool_cleanupusers
 * @copyright 2017 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archiveduser {
    /** @var int The id of the user */
    public $id;

    /** @var int 1 if the user is suspended 0 otherwise */
    public $suspended;

    /** @var int timestamp */
    public $lastaccess;

    /** @var string username */
    public $username;

    /** @var int user deleted? */
    public $deleted;

    /** @var int user authentication method */
    public $auth;

    /** @var string user email (needed for display) */
    public $email;

    /** @var string userstatus checker */
    public $checker;

    /** @var int timecreated, temporary value */
    public $timecreated;

    /**
     * Archiveduser constructor.
     * @param $id
     * @param $suspended
     * @param $lastaccess
     * @param $username
     * @param $deleted
     * @param $auth
     * @param $timecreated is only required for suspension and backdate
     * @param $checker
     */
    public function __construct($id, $suspended, $lastaccess, $username, $deleted,
                                $auth, $email, $timecreated, $checker = '') {
        $this->id = $id;
        $this->suspended = $suspended;
        $this->lastaccess = $lastaccess;
        $this->username = $username;
        $this->deleted = $deleted;
        $this->auth = $auth;
        $this->email = $email;
        $this->timecreated = $timecreated;
        $this->checker = $checker;
    }

    /**
     * Suspends the user.
     * Therefore, makes an entry in tool_cleanupusers and tool_cleanupusers_archive tables.
     * Throws an exception when the user is already suspended.
     * @throws cleanupusers_exception
     */
    public function archive_me($checker, $dryrun, $timestamp): array {
        global $DB;
        // Get the current user.
        $user = \core_user::get_user($this->id);
/*        if ($user->suspended == 1) {
            throw new cleanupusers_exception("Failed to suspend " . $user->username .
                " : user is already suspended");
        } else */
        if (!($user->username == \core_user::clean_field($user->username, 'username'))) {
            throw new cleanupusers_exception("Failed to suspend " . $user->username .
                " : username is not cleaned");
        } else {
            if (!$dryrun) {
                $transaction = $DB->start_delegated_transaction();

                // We are already getting the shadowuser here to keep the original suspended status.
                $shadowuser = clone $user;
                // The user might be logged in, so we must kill his/her session.
                $user->suspended = 1;
                global $CFG;
                if ($CFG->version > 2024100700) {
                    // from Moodle 4.5 use destroy instead of kill
                    manager::destroy_user_sessions($user->id);
                } else {
                    manager::kill_user_sessions($user->id);
                }
                user_update_user($user, false);
                // Document time of editing user in Database.
                // In case there is no entry in the tool table make a new one.
                // $timestamp = time();
                if (!$DB->record_exists('tool_cleanupusers', ['id' => $user->id])) {
                    $DB->insert_record_raw('tool_cleanupusers', [
                        'id' => $user->id,
                        'archived' => 1,
                        'timestamp' => $timestamp,
                        'checker' => $checker], true, false, true);
                }
                // Insert copy of user in second DB and replace user in main table when entry was successful.
                if ($DB->record_exists('tool_cleanupusers_archive', ['id' => $shadowuser->id])) {
                    $DB->delete_records('tool_cleanupusers_archive', ['id' => $shadowuser->id]);
                }
                $DB->insert_record_raw('tool_cleanupusers_archive', $shadowuser, true, false, true);
                // Replaces the current user with a pseudo_user that has no reference.
                $cloneuser = $this->give_suspended_pseudo_user($shadowuser->id, $timestamp);
                user_update_user($cloneuser, false);

                $transaction->allow_commit();
            }
        }

        return self::prepare_user_for_export($user);
    }

    /**
     * Reactivates the user.
     * Therefore, deletes the entry in the tool_cleanupusers table and throws an exception when no entry is available.
     * @throws cleanupusers_exception
     */
    public function activate_me() : void {
        global $DB;
        // Get the current user.
        $user = \core_user::get_user($this->id);
        if (!$DB->record_exists('tool_cleanupusers', ['id' => $user->id])) {
            // User was suspended manually.
            throw new cleanupusers_exception("Failed to reactivate " . $user->username .
                " : user not suspended by the plugin");
        } else {
            // User was suspended by the plugin.
            if (!$DB->record_exists('tool_cleanupusers_archive', ['id' => $user->id])) {
                throw new cleanupusers_exception("Failed to reactivate " . $user->username .
                    " : user suspended by the plugin has no entry in archive");
            } else {
                $shadowuser = $DB->get_record('tool_cleanupusers_archive', ['id' => $user->id]);
                if ($DB->record_exists('user', ['username' => $shadowuser->username])) {
                    throw new cleanupusers_exception("Failed to reactivate " . $user->username .
                        " : user suspended by the plugin already in user table");
                } else {
                    $transaction = $DB->start_delegated_transaction();

                    // Both records exist, so we have a user which can be reactivated.
                    // If the user is in table replace data.
                    // Ensure that reactivated user is not suspended anymore.
                    // $shadowuser->suspended = 0; //????
                    user_update_user($shadowuser, false);
                    // Delete records from tool_cleanupusers and tool_cleanupusers_archive tables.
                    $DB->delete_records('tool_cleanupusers', ['id' => $user->id]);
                    $DB->delete_records('tool_cleanupusers_archive', ['id' => $user->id]);

                    $transaction->allow_commit();
                }
            }
        }
    }

    /**
     * Deletes the user.
     *
     * Therefore,
     * (1) Deletes the entry in the tool_cleanupusers and the tool_cleanupusers_archive tables.
     * (2) Hashes the username with the sha256 function.
     * (3) Calls the moodle core delete_user function.
     *
     * @throws cleanupusers_exception
     */
    public function delete_me($dryrun): array {
        global $DB;
        // Get the current user.
        $user = \core_user::get_user($this->id);
        if (!$DB->record_exists('tool_cleanupusers', ['id' => $user->id])) {
            // User was suspended manually.
            throw new cleanupusers_exception("Failed to delete " . $user->username .
                " : user not suspended by the plugin");
        }
        // User was suspended by the plugin.
        if (!$DB->record_exists('tool_cleanupusers_archive', ['id' => $user->id])) {
            throw new cleanupusers_exception("Failed to delete " . $user->username .
                " : user suspended by the plugin has no entry in archive");
        }

        // read correct user data from archive data
        $deleteduserdata = $DB->get_record('tool_cleanupusers_archive', array('id' => $this->id));

        if (!$dryrun) {
            $transaction = $DB->start_delegated_transaction();

            // Deletes the records in both plugin tables.
            $DB->delete_records('tool_cleanupusers', ['id' => $user->id]);
            $DB->delete_records('tool_cleanupusers_archive', ['id' => $user->id]);

            // To secure that plugins that reference the user table do not fail create empty user with a hash as username.
            $newusername = hash('md5', $user->username);
            // Checks whether the username already exist (possible but unlikely).
            // In the unlikely case that hash(username) exist in the table, while loop generates new username.
            while ($DB->record_exists('user', ["username" => $newusername])) {
                $tempname = $newusername;
                $newusername = hash('md5', $user->username . $tempname);
            }
            $user->username = $newusername;
            user_update_user($user, false);
            global $CFG;
            if ($CFG->version > 2024100700) {
                // from Moodle 4.5 use destroy instead of kill
                manager::destroy_user_sessions($user->id);
            } else {
                manager::kill_user_sessions($user->id);
            }
            // Core Function has to be executed finally.
            // It can not be executed earlier since moodle then prevents further operations on the user.
            // The Function adds @unknownemail.invalid. and a timestamp to the username.
            // It is secured, that the username is below 100 characters since sha256 produces 64 characters and the...
            // additional string has only 32 characters.
            user_delete_user($user);

            $transaction->allow_commit();
        }

        return self::prepare_user_for_export($deleteduserdata);
    }

    /**
     * Creates an empty user with 'anonym + id' as username and 'Anonym' as Firstname.
     *
     * @param int $id
     * @param int $timestamp
     * @return object
     */
    private function give_suspended_pseudo_user($id, $timestamp) {
        $cloneuser = new \stdClass();
        $cloneuser->id = $id;
        // Usernames have to be unique therefore the id is used.
        $config = get_config('tool_cleanupusers');

        $cloneuser->username = $config->suspendusername . $id; // 'anonym' . $id;
        $cloneuser->firstname = $config->suspendfirstname; // 'Anonym';
        $config->suspendemail = trim($config->suspendemail);
        if (strlen($config->suspendemail) > 0) {
            $cloneuser->email = $cloneuser->username . '@' . $config->suspendemail;
        } else {
            $cloneuser->email = '';
        }
        $cloneuser->lastname = $config->suspendlastname; // '';
        $cloneuser->suspended = 1;
        $cloneuser->phone1 = '';
        $cloneuser->phone2 = '';
        $cloneuser->institution = '';
        $cloneuser->department = '';
        $cloneuser->address = '';
        $cloneuser->idnumber = '';
        $cloneuser->city = '';
        $cloneuser->country = '';
        $cloneuser->lang = '';
        $cloneuser->calendartype = '';
        $cloneuser->firstaccess = 0;
        $cloneuser->lastaccess = 0;
        $cloneuser->currentlogin = 0;
        $cloneuser->lastlogin = 0;
        $cloneuser->secret = '';
        $cloneuser->picture = 0;
        $cloneuser->description = '';
        $cloneuser->timemodified = time();
        $cloneuser->timecreated = $timestamp;
        $cloneuser->imagealt = '';
        $cloneuser->lastnamephonetic = '';
        $cloneuser->firstnamephonetic = '';
        $cloneuser->middlename = '';
        $cloneuser->alternatename = '';
        $cloneuser->moodlenetprofile = '';

        return $cloneuser;
    }

    /**
     * @param bool|\stdClass $user
     * @return array
     */
    protected static function prepare_user_for_export(\stdClass $user): array {
        $archiveduser = (array)$user;
        if (!empty($user->lastaccess) && $user->lastaccess != 0) {
            $archiveduser['lastaccess'] = date('d.m.Y h:i:s', $user->lastaccess);
        } else {
            $archiveduser['lastaccess'] = 'never logged in';
        }
        if (!empty($user->timecreated) && $user->timecreated != 0) {
            $archiveduser['timecreated'] = date('d.m.Y h:i:s', $user->timecreated);
        } else {
            $archiveduser['timecreated'] = '???';
        }

        // extract a few attributes in order to save memory.
        $wantedkeys = ['id', 'username', 'firstname', 'lastname', 'lastaccess', 'auth', 'suspended', 'timecreated', 'email'];
        return array_intersect_key($archiveduser, array_flip($wantedkeys));
    }
}
