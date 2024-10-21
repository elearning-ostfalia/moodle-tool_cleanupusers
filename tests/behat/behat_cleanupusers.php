<?php
// This file is part of ProFormA Question Type for Moodle
//
// ProFormA Question Type for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// ProFormA Question Type for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat extensions for tool_cleanupusers
 *
 * @package   tool_cleanupusers
 * @copyright 2024 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

define('DEFAULT_USERNAME', 'user');

class behat_cleanupusers extends behat_base {
    /**
     * Expect user $username have been archived.
     * => set archive timestamp xxx days before
     *
     * @Then /^simulate that "(?P<days_string>(?:[^"]|\\")*)" days have passed since archiving of "(?P<username_string>(?:[^"]|\\")*)"$/
     * @return void
     */
    public function simulate_that_time_passes_since_archiving($days, $username) {
        global $DB;
        // Username has been changed in user table. So the id lookup
        // must be performed in the archive table
        $record = $DB->get_record('tool_cleanupusers_archive', ['username' => $username], 'id',
            MUST_EXIST);
        $record = $DB->get_record('tool_cleanupusers', ['id' => $record->id], '*', MUST_EXIST);
        $record->timestamp -= $days * 60 * 60 * 24;
        $DB->update_record('tool_cleanupusers', $record);
    }

    /**
     * Expect user $username have been archived.
     * => set archive timestamp xxx days before
     *
     * @Then /^simulate that "(?P<days_string>(?:[^"]|\\")*)" days have passed since archiving from "(?P<username1_string>(?:[^"]|\\")*)" to "(?P<username2_string>(?:[^"]|\\")*)"$/
     * @return void
     */
    public function simulate_that_time_passes_since_archiving_between($days, $usernamefrom, $usernameto) {
        global $DB;
        $to = substr($usernameto, strlen(DEFAULT_USERNAME));
        $from = substr($usernamefrom, strlen(DEFAULT_USERNAME));
        // var_dump($from);
        // var_dump($to);

        // var_dump($DB->get_records('tool_cleanupusers_archive', null, '', 'id, username'));
/*        $sql1 = 'select id, username, ' . $DB->sql_substr("username", strlen(DEFAULT_USERNAME)+1) .
                ' from {tool_cleanupusers_archive} ';
        var_dump($sql1);
        var_dump($DB->get_records_sql($sql1));

        $sql1 = 'select a.id 
                from {tool_cleanupusers_archive} a  
                where '
            . $DB->sql_cast_char2int($DB->sql_substr("a.username", strlen(DEFAULT_USERNAME)+1)) . ' >= :from and '
            . $DB->sql_cast_char2int($DB->sql_substr("a.username", strlen(DEFAULT_USERNAME)+1)) . ' <= :to';
        var_dump($sql1);
        var_dump($DB->get_records_sql($sql1, ['from' => $from, 'to' => $to]));
*/
        if ($DB->get_dbfamily() == 'postgres') {
            $sql = 'update {tool_cleanupusers} as u
            set timestamp = u.timestamp - ' . ($days * 60 * 60 * 24). '
                from {tool_cleanupusers_archive} a WHERE a.id = u.id 
                AND '
                . $DB->sql_cast_char2int($DB->sql_substr("a.username", strlen(DEFAULT_USERNAME)+1)) . ' >= :from AND '
                . $DB->sql_cast_char2int($DB->sql_substr("a.username", strlen(DEFAULT_USERNAME)+1)) . ' <= :to';
        } else {
            $sql = 'update {tool_cleanupusers} u, {tool_cleanupusers_archive} a 
            set u.timestamp = u.timestamp - ' . ($days * 60 * 60 * 24). '
                WHERE a.id = u.id 
                AND '
                . $DB->sql_cast_char2int($DB->sql_substr("a.username", strlen(DEFAULT_USERNAME)+1)) . ' >= :from AND '
                . $DB->sql_cast_char2int($DB->sql_substr("a.username", strlen(DEFAULT_USERNAME)+1)) . ' <= :to';
        }

        // echo $sql;
        $DB->execute($sql, ['from' => $from, 'to' => $to]);
    }

    /**
     * suspend/archive username
     *
     * @Then /^I archive "(?P<username_string>(?:[^"]|\\")*)"$/
     * @return void
     */
    public function I_archive($username) {
        global $DB;
        $record = $DB->get_record('user', ['username' => $username], 'id', MUST_EXIST);
        $this->execute('behat_general::i_click_on', ['.imggroup-' . $record->id, 'css']);

    }

    /**
     * suspend/archive username
     *
     * @Then /^I delete "(?P<username_string>(?:[^"]|\\")*)"$/
     * @return void
     */
    public function I_delete($username) {
        global $DB;
        // Look up identifier
        $record = $DB->get_record('tool_cleanupusers_archive', ['username' => $username], 'id',
            MUST_EXIST);

        $session = $this->getSession();
        $webDriver = $session->getDriver()->getWebDriver();

        $elementclass = "imggroup-" . $record->id;
        $element = './/*[contains(@class,\''. $elementclass .'\')]';
        echo $element .PHP_EOL;

        echo 'click on menu item' . PHP_EOL;
        $js = "document.evaluate(\"".$element."\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue.click()";
        try {
            $output = $this->getSession()->getDriver()->evaluateScript($js);
            // $webDriver->executeAsyncScript($js);
        } catch (Exception $e) {
            // Document was unloaded??
            echo 'Exception caught: ' . $e->getMessage();
        }

        // Switch to window if it exists
        try {
            $this->getSession()->switchToWindow(null);
        } catch(Exception $e) {
            echo 'Exception caught: ' . $e->getMessage();
        }

/*        sleep(2000);

        echo 'switch to alert' . PHP_EOL;
        $alert = $webDriver->switchTo()->alert();

        echo 'get state' . PHP_EOL;
        $state = $webDriver->executeAsyncScript('return document.readyState');
        echo 'state async ' . $state . PHP_EOL;
  */

/*
        $this->execute('behat_general::i_click_on', ['.imggroup-' . $record->id, 'css']);

        // Confirms the delete.
        if ($this->running_javascript()) {
            $title = get_string('confirm-delete-title', 'tool_cleanupusers');

            $this->execute('behat_general::i_click_on_in_the', [
                get_string('delete'),
                'button',
                $title,
                'dialogue',
            ]);
        }
*/
    }

    /**
     * reactivate username
     *
     * @Then /^I reactivate "(?P<username_string>(?:[^"]|\\")*)"$/
     * @return void
     */
    public function I_reactivate($username) {
        global $DB;
        // Look up identifier
        $record = $DB->get_record('tool_cleanupusers_archive', ['username' => $username], 'id',
            MUST_EXIST);
        $this->execute('behat_general::i_click_on', ['.imggroup-' . $record->id, 'css']);
    }

    /**
     * create large number of users
     *
     * @Then /^create "(?P<count_string>(?:[^"]|\\")*)" users$/
     * @return void
     */
    public function create_users($count) {
        $generator = testing_util::get_data_generator(); // $this->get_data_generator();
        for ($i = 1; $i <= $count; $i++) {
            $user = $generator->create_user([
                'username' => DEFAULT_USERNAME . $i,
                'timecreated' => time() - (60*60*24*366) // created one year ago
            ]);
        }
    }

    // OLD approach:
    //  And I set the field with xpath "//select[@name='action']" to "users to be archived by"
    //  And I set the field with xpath "//select[@name='subplugin']" to "nocoursechecker"

    /**
     * select checker on archiving page
     *
     * @Then /^I select "(?P<checker_string>(?:[^"]|\\")*)" checker on archiving page$/
     * @return void
     */
    public function select_archiving_checker($checker) {
        $xpath = "//div[label[contains(., 'Users to be archived')]]/div";
        $this->execute("behat_general::i_click_on", [$xpath, 'xpath_element']);

        $xpath = "//*[contains(text(), '{$checker}')]";
        $this->execute("behat_general::i_click_on", [$xpath, 'xpath_element']);
    }

    /**
     * navigate to reactivation page
     *
     * @Then /^I navigate to "(?P<checker_string>(?:[^"]|\\")*)" archive page/
     * @return void
     */
    public function navigate_to_new_archive_page($title) {
        $xpath = "//div[label[contains(., 'Users to be reactivated') or contains(., 'All archived users') or contains(., 'Users to be deleted')]]/div";
        $this->execute("behat_general::i_click_on", [$xpath, 'xpath_element']);

        $xpath = "//*[contains(text(), '{$title}')]";
        $this->execute("behat_general::i_click_on", [$xpath, 'xpath_element']);
    }

    /**
     * select checker on archive page
     *
     * @Then /^I select "(?P<checker_string>(?:[^"]|\\")*)" checker on archive page$/
     * @return void
     */
    public function select_archive_checker($checker) {
        $xpath = "//div[label[starts-with(., 'by ')]]/div";
        $this->execute("behat_general::i_click_on", [$xpath, 'xpath_element']);

        $xpath = "//*[contains(text(), '{$checker}')]";
                $this->execute("behat_general::i_click_on", [$xpath, 'xpath_element']);
    }

}