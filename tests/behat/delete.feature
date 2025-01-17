@tool @tool_cleanupusers
Feature: Cleanup settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | relativedatesmode | timecreated    | lastaccess | suspended | description            |
      | user1    | Student   | Miller1   | 1                 | ## -320 days## | ## -11 days ## | 0     | Last Login, student            |
      | user2    | Teaching  | Miller2   | 1                 | ## -32 days##  | ## -9 days## | 0       |                        |
      | user3    | Student   | Miller3   | 1                 | ## -15 days##  | 0  | 0                 | Never Login            |
      | user4    | Student   | Miller4   | 1                 | ## -14 days##  | 0  | 0                 |                        |
      | user5    | Student   | Miller5   | 1                 | ## -40 days##  | 0  | 0                 | No active course       |
      | user6    | Student   | Miller6   | 1                 | ## -12 days##  | 0  | 0                 |                        |
      | user7    | Student   | Miller7   | 1                 | ## -12 days##  | 0  | 0                 |                        |
      | user8    | Student   | Miller8   | 1                 | ## -12 days##  | 0  | 0                 |                        |
      | user9    | Student   | Miller9   | 1                 | ## -12 days##  | ## -9 days##  | 1      | Suspended              |
      | user0    | Student   | Miller10  | 1                 | ## -15 days##  | 0  | 0                 | neverlogin AND nocouse |
      | user_1a  | Teacher   | Miller1   | 1                 | ## -320 days## | ## -31 days ## | 0     | Last Login, teacher             |
      | user_1b  | Teacher   | Miller1   | 1                 | ## -320 days## | ## -11 days ## | 0     | Last Login waiting, teacher             |
      | user_5a  | Teacher   | Miller5   | 1                 | ## -12 days##  | 0  | 0                 | No active course, teacher       |
      | user_5b  | Student   | Miller5   | 1                 | ## -12 days##  | 0  | 0                 | No (active) course, waiting       |
      | user_5c  | Student   | Miller5   | 1                 | ## -40 days##  | 0  | 0                 | No (active) course      |
      | newadmin | New       | AdminUser | 1                 | ## -15 days##  | 0  | 0                 | new admin |

    And the following "courses" exist:
      | fullname  | shortname  | category  | relativedatesmode  | startdate      | enddate     | visible |
      | Active1   | CA1         | 0         | 1                  | ##-32 days##  |             | 1       |
      | Active2   | CA2         | 0         | 1                  | ##-32 days##  | ##32 days## | 1       |
      | Active3   | CA3         | 0         | 1                  | ## 9 days##   | ##100days## | 1       |
      | Active4   | CA4         | 0         | 1                  | ## 9 days##   |             | 1       |
      | Inactive1 | CI1         | 0         | 1                  | ##-32 days##  | ##-10days## | 1       |
      | Inactive2 | CI2         | 0         | 1                  | ##-32 days##  | ##10days##  | 0       |

    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | CA1     | student |
      # user1 as student is handled by lastlogin checker
      # in order to see handling with different role
      # user_1a is added (same attribute but teacher role)
      | user_1a  | CA1     | editingteacher |
      | user_1b  | CA1     | editingteacher |
      | user2    | CA2     | student |
      | user3    | CA3     | editingteacher |
      | user4    | CA4     | student |
      | user5    | CI1     | student |
      | user_5a  | CI1     | editingteacher |
      | user6    | CI2     | student |

    # Values are set per checker as otherwise only one value
    # with the same first column will be set (bug?)
    And the following config values are set as admin:
      | userstatus_plugins_enabled | lastloginchecker,nocoursechecker,neverloginchecker,suspendedchecker  | |
      | auth_method | manual  | userstatus_nocoursechecker |
      | keepteachers | 1  | userstatus_nocoursechecker |
      | deletetime | 15  | userstatus_nocoursechecker |
      | waitingperiod | 30  | userstatus_nocoursechecker |
    And the following config values are set as admin:
      | auth_method | manual  | userstatus_lastloginchecker |
      | suspendtime | 10  | userstatus_lastloginchecker |
      | deletetime | 100  | userstatus_lastloginchecker |
      | keepteachers | 0 | userstatus_lastloginchecker |
      | suspendtimeteacher | 20  | userstatus_lastloginchecker |
    And the following config values are set as admin:
      | suspendtime | 14  | userstatus_neverloginchecker |
      | deletetime | 200  | userstatus_neverloginchecker |
      | auth_method | manual  | userstatus_neverloginchecker |
    And the following config values are set as admin:
      | auth_method | manual  | userstatus_suspendedchecker |
      | suspendtime | 0  | userstatus_suspendedchecker |
      | deletetime | 100  | userstatus_suspendedchecker |

  @javascript
  Scenario: Preview cleanup users 2
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And simulate that "101" days have passed since archiving of "user1"

    When I navigate to "Users > Clean up users > Pending cleanup actions" in site administration

    And I should see "user1"

    # Check if user is visible ob export
    And I set the field with xpath "(//select[@name='dataformat'])[2]" to "HTML table"
    And I click on "(//button[contains(text(), 'Download')])[2]" "xpath_element"
    And I should see "user1"
    And I press the "back" button in the browser

    # delete in preview page
    When I delete "user1"
    Then "Completely delete user" "dialogue" should exist
    And I should see "Do you really want to delete"
    And I should see "Student Miller1"

    When I click on "Cancel" "button" in the "Completely delete user" "dialogue"
    Then I should see "user1"

    # seems to fail in behat test but works in manual test ..
    When I delete "user1"
    Then "Completely delete user" "dialogue" should exist
    And I should see "Student Miller1"

    When I press "Delete"
    Then I should see "User 'user1' has been deleted."

    When I press "Continue"


  @javascript
  Scenario: Manually delete user (preselected by lastloginchecker)
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And simulate that "101" days have passed since archiving of "user1"


    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be deleted" archive page
    And I select "Last Login Checker" checker on archive page
    And I should see "user1"

    When I delete "user1"
    Then "Completely delete user" "dialogue" should exist
    And I should see "Do you really want to delete"
    And I should see "Student Miller1"

    When I click on "Cancel" "button" in the "Completely delete user" "dialogue"
    Then I should see "user1"

    When I delete "user1"
    Then "Completely delete user" "dialogue" should exist
    And I should see "Student Miller1"

    When I press "Delete"
    Then I should see "User 'user1' has been deleted."

    When I press "Continue"
    Then I should see "Users to be deleted"
    And I should see "Last Login Checker"
    And I should not see "user1"

    When I navigate to "All archived users" archive page
    Then I should not see "user1"

  @javascript
  Scenario: Automatically delete user (lastloginchecker)
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And simulate that "101" days have passed since archiving of "user1"
    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be deleted" archive page
    And I select "Last Login Checker" checker on archive page
    And I should see "user1"

    When I run the scheduled task "\tool_cleanupusers\task\delete_user_task"
    And I reload the page

    Then I should see "Users to be deleted"
    And I should see "Last Login Checker"
    And I should not see "user1"

    When I navigate to "All archived users" archive page
    Then I should not see "user1"

  @javascript
  Scenario: Do not delete if reactivate possible
    Given I log in as "admin"
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"

    And the following config values are set as admin:
      | deleteifneverloggedin | 1  | userstatus_nocoursechecker |

    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be deleted" archive page
    When I select "No active course Checker" checker on archive page
    Then I should see "user5"
    And I should see "user_5c"
    And I should see "user6"

    # make invisible course visble
    And I go to the courses management page
    And I should see the "Course categories and courses" management page
    And I toggle visibility of course "Inactive2" in management listing

    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be deleted" archive page
    When I select "No active course Checker" checker on archive page
    And I should not see "user6"

  @javascript
  Scenario: Delete correct users
    # Precondtion: several users can be deleted by several checkers
    # ensure that only the appropriate users are deleted
    # (disable checker and check if the users belonging to that checker are not deleted)
    Given I log in as "admin"
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    # check that Never Login Checker and No active course Checker do not delete
    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be deleted" archive page
    When I select "Never Login Checker" checker on archive page
    Then I should see "Nothing to display"
    When I select "No active course Checker" checker on archive page
    Then I should see "Nothing to display"

    # update settings
    And the following config values are set as admin:
      | deleteifneverloggedin | 1  | userstatus_neverloginchecker |
    And the following config values are set as admin:
      | deleteifneverloggedin | 1  | userstatus_nocoursechecker |

    # check that Never Login Checker and No active course Checker will delete
    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be deleted" archive page

    When I select "Never Login Checker" checker on archive page
    Then I should see "user3"
    And I should see "user4"

    # And I pause

    When I select "No active course Checker" checker on archive page
    Then I should see "user5"
    And I should see "user_5c"
    And I should see "user6"

    # update settings so that No active course Checker will not delete
    And the following config values are set as admin:
      | deleteifneverloggedin | 0  | userstatus_nocoursechecker |

    When I select "Never Login Checker" checker on archive page
    Then I should see "user3"
    And I should see "user4"

    When I select "No active course Checker" checker on archive page
    Then I should see "Nothing to display"

    # run delete task and check
    When I run the scheduled task "\tool_cleanupusers\task\delete_user_task"
    And I reload the page
    Then I should see "Nothing to display"

    When I select "No active course Checker" checker on archive page
    Then I should see "Nothing to display"

    When I navigate to "All archived users" archive page
    Then I should see "user5"
    And I should see "user_5c"
    And I should see "user6"
    And I should not see "user3"
    And I should not see "user4"
