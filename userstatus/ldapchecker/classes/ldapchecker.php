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
 * Sub-plugin ldapchecker.
 *
 * @package   userstatus_ldapchecker
 * @copyright 2016/17 N. Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace userstatus_ldapchecker;

use core\session\exception;
use tool_cleanupusers\archiveduser;
use tool_cleanupusers\userstatusinterface;
use tool_cleanupusers\userstatuschecker;

defined('MOODLE_INTERNAL') || die;

/**
 * Class that checks the status of different users depending on the time they have not signed in for.
 *
 * @package    userstatus_ldapchecker
 * @copyright  2016/17 N Herrmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ldapchecker extends userstatuschecker {

    /** @var array lookuptable for ldap users */
    protected $lookup = array();

    private $testing = false;

    /**
     * This constructor sets timesuspend and timedelete from days to seconds.
     * @throws \dml_exception
     */
    public function __construct($testing = false) {
        parent::__construct(get_class(), $testing);

        // Calculates days to seconds.
        $this->testing = $testing;
    }

    private function read_ldap_users() {
        if (defined('BEHAT_SITE_RUNNING')) {
            // skip connecting to ldap in behat tests.
            // Add fake user to array because empry LDAP result would result in failure.
            $this->lookup['12345678'] = true;
            return;
        }
        if ($this->testing || defined('PHPUNIT_COMPOSER_INSTALL')) {
            // skip connecting to ldap in Phpunit tests.
            return;
        }

        $ldap = ldap_connect($this->config->host_url) or die("Could not connect to $this->config->host_url");
        try {
            $bind = ldap_bind($ldap, $this->config->bind_dn, $this->config->bind_pw); // returns 1 if correct

            if($bind) {
                $this->log("ldap_bind successful");

                $contexts = $this->config->contexts;

                $uid = $this->config->ldap_username_attribute;
                $attributes = [$uid];
                $filter = $this->config->search_filter; // '(cn=*)';
                $search = ldap_search($ldap, $contexts, $filter, $attributes) or die("Error in search Query: " . ldap_error($ldap));
                $result = ldap_get_entries($ldap, $search);

                foreach ($result as $user) {
                    if(isset($user[$uid])) {
                        foreach ($user[$uid] as $cn) {
                            $this->lookup[$cn] = true;
                        }
                    }
                }

                $this->log("ldap server sent " . count($this->lookup) . " users");

                if (count($this->lookup) == 0) {
                    throw new exception("empty result set from LDAP server");
                }
                global $SESSION;
                $SESSION->cleanupusers_LDAP_cache = $this->lookup;
                // cache is valid for 10 minutes
                $SESSION->cleanupusers_LDAP_cache_ttl = time() + (60 * 10);

            } else {
                $this->log("ldap_bind failed");
                // fatal error!
                throw new exception("cannot connect to LDAP server");
            }
        } finally {
            // Close LDAP connection.
            ldap_close($ldap);
        }
    }

    public function invalidate_cache(): void {
        global $SESSION;
        unset($SESSION->cleanupusers_LDAP_cache);
        unset($SESSION->cleanupusers_LDAP_cache_ttl);
    }

    private function init()  {
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            return;
        }
        if (count($this->lookup) == 0) {
            global $SESSION;
            if (isset($SESSION->cleanupusers_LDAP_cache) && count($SESSION->cleanupusers_LDAP_cache) > 0 &&
                isset($SESSION->cleanupusers_LDAP_cache_ttl) && $SESSION->cleanupusers_LDAP_cache_ttl > time()) {
                // debugging('using ldap cache');
                $this->lookup = $SESSION->cleanupusers_LDAP_cache;
                return;
            }

            $this->read_ldap_users();
            if (count($this->lookup) == 0) {
                throw new \moodle_exception('No users from LDAP available');
            }
        }
    }


    /**
     * part of SQL where clause to perform the check for suspension:
     * no user data from Moodle data is considered, not even the suspended flag.
     * => if a user authenticated by LDAP is suspended manually,
     * then he will always be archived if he is removed from LDAP.
     * If this is not what is desired then the suspended checker should be
     * moved in front of the ldap checker
     *
     * @return array
     */
    public function condition_suspend_sql(): array {
        return ["" , null];
    }

    /**
     * @throws \moodle_exception
     * @throws exception
     */
    public function shall_suspend($user): bool {
        // check initialisation state (todo: should not be checked for every user!)
        $this->init();
        // var_dump($this->lookup);
        return (!array_key_exists($user->username, $this->lookup));
    }

    public function shall_reactivate($user): bool {
        return !$this->shall_suspend($user);
    }


    /** does not use suspend time value */
    public function needs_suspendtime(): bool {
        return false;
    }

    public function get_to_suspend() {
        $this->init(); // read ldap users
        return parent::get_to_suspend();
    }

    public function fill_ldap_response_for_testing($dummy_ldap) {
        if ($this->testing || defined('PHPUNIT_COMPOSER_INSTALL')) {
            // only for testing
            $this->lookup = $dummy_ldap;
        }
    }
}
