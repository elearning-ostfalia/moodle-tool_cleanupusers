<?php

namespace tool_cleanupusers;

class userstatus_base_test extends \advanced_testcase
{
    protected $generator = null;
    protected $checker = null;
    /**
     * @param array $returnsuspend
     * @param \stdClass|null $user
     * @return array
     */
    protected function assertEqualsUsersArrays(array $returnsuspend, ?\stdClass $user)
    {
        $this->assertEquals(1, count($returnsuspend));

        $this->assertEqualsCanonicalizing(array_map(fn($user) => $user->username, $returnsuspend), [$user->username]);

        // Compare content
        $archuser = reset($returnsuspend); // get one and only element from array
        $array2 = (array)($archuser);
        $checker = get_class($this->checker);
        // strip namespace from checker class
        $checker = substr($checker, strlen("userstatus_nocoursechecker\\"));
        $user->checker = $checker; // checker is not contained in user => add for check
        $this->assertEquals($array2, array_intersect_assoc((array)$user, $array2));
    }

    protected function create_test_user($username, $extra_attributes = []) {
        return $this->generator->create_user(array_merge(
            ['username' => $username, 'auth' => AUTH_METHOD],
            $extra_attributes));
    }

    protected function create_user_and_enrol($username = '', $course = null) {
        if (!empty($username)) {
            $user = $this->create_test_user($username);
            if ($course != null) {
                $this->generator->enrol_user($user->id, $course->id);
            }
            return $user;
        }
        return null;
    }

    protected function archive($user, $when, $username) {
        $this->insert_into_metadata_table($user, $when);
        $this->insert_into_archive($user, $username);
    }

    protected function insert_into_archive($user, $username) {
        global $DB;
        $DB->insert_record_raw('tool_cleanupusers_archive', ['id' => $user->id, 'auth' => 'shibboleth',
            'username' => $username,
            'suspended' => $user->suspended, 'timecreated' => $user->timecreated],
            true, false, true);
    }

    protected function insert_into_metadata_table($user, $when) {
        global $DB;
        $DB->insert_record_raw('tool_cleanupusers',
            ['id' => $user->id, 'archived' => true,
                'timestamp' => $when, 'checker' => 'nocoursechecker'], true, false, true);
    }
}