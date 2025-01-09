@tool @tool_cleanupusers
Feature: Cleanup settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | relativedatesmode | timecreated    | lastaccess | suspended |
      | user1    | Teacher   | Miller1   | 1                 | ## -320 days## | ## -11 days ## | 0     |
      | user_t   | Teacher   | Miller1   | 1                 | ## -320 days## | ## -11 days ## | 0     |
      | user2    | Teaching  | Miller2   | 1                 | ## -32 days##  | ## -9 days## | 0       |
      | user3    | Student   | Miller3   | 1                 | ## -15 days##  | 0  | 0                 |
      | user4    | Student   | Miller4   | 1                 | ## -14 days##  | 0  | 0                 |
      | user5    | Student   | Miller5   | 1                 | ## -12 days##  | 0  | 0                 |
      | user6    | Student   | Miller6   | 1                 | ## -12 days##  | 0  | 0                 |
      | user7    | Student   | Miller7   | 1                 | ## -12 days##  | 0  | 0                 |
      | user8    | Student   | Miller8   | 1                 | ## -12 days##  | 0  | 0                 |
      | user9    | Student   | Miller9   | 1                 | ## -12 days##  | ## -9 days##  | 1      |

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
      | user_t   | CA1     | editingteacher |
      | user2    | CA2     | student |
      | user3    | CA3     | editingteacher |
      | user4    | CA4     | student |
      | user5    | CI1     | editingteacher |
      | user6    | CI2     | student |

    # Values are set per checker as otherwise only one value
    # with the same first column will be set (bug?)
    And the following config values are set as admin:
      | userstatus_plugins_enabled | lastloginchecker,nocoursechecker,neverloginchecker,suspendedchecker  | |
      | auth_method | manual  | userstatus_nocoursechecker |
#      | suspendtime | 20  | userstatus_nocoursechecker |
      | deletetime | 15  | userstatus_nocoursechecker |
    And the following config values are set as admin:
      | auth_method | manual  | userstatus_lastloginchecker |
      | suspendtime | 10  | userstatus_lastloginchecker |
      | deletetime | 100  | userstatus_lastloginchecker |
    And the following config values are set as admin:
      | suspendtime | 14  | userstatus_neverloginchecker |
      | deletetime | 200  | userstatus_neverloginchecker |
      | auth_method | manual  | userstatus_neverloginchecker |
    And the following config values are set as admin:
      | auth_method | manual  | userstatus_suspendedchecker |
      | suspendtime | 0  | userstatus_suspendedchecker |
      | deletetime | 100  | userstatus_suspendedchecker |

  @javascript
  Scenario: Manually reactivate user (preselected by nocoursechecker)
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"

    # make invisible course visble
    And I go to the courses management page
    And I should see the "Course categories and courses" management page
    And I toggle visibility of course "Inactive2" in management listing

    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be reactivated" archive page
    And I select "No active course Checker" checker on archive page

    And I should see "user6"
    And I reactivate "user6"
    And I should see "User 'user6' has been reactivated."
    And I press "Continue"
    And I should see "Users to be reactivated"
    And I should see "No active course Checker"
    And I should not see "user6"
    And I navigate to "All archived users" archive page
    And I should not see "user6"

  @javascript
  Scenario: Automatically reactivate user (nocoursechecker)
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"

    # make invisible course visble
    And I go to the courses management page
    And I should see the "Course categories and courses" management page
    And I toggle visibility of course "Inactive2" in management listing

    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be reactivated" archive page
    And I select "No active course Checker" checker on archive page

    And I should see "user6"

    When I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And I reload the page

    Then I should see "Users to be reactivated"
    And I should see "No active course Checker"
    And I should not see "user6"

    When I navigate to "All archived users" archive page
    Then I should not see "user6"

  @javascript
  Scenario: Manually reactivate user (empty filter)
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be reactivated" archive page
    And I select "Last Login Checker" checker on archive page
    And I should see "Nothing to display"

  @javascript
  Scenario: Manually reactivate user (all archived users filter)
    Given I log in as "admin"
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I should see "All archived users"
    And I should see "user1"
    And I should not see "user_t"
    And I should see "user3"
    And I should see "user4"
    And I should see "user5"
    And I should see "user6"
    And I should see "user7"
    And I should see "user8"
    When I reactivate "user7"
    Then I should see "User 'user7' has been reactivated"
    And I press "Continue"
    And I should see "All archived users"
    # And I navigate to "Users > Clean up users > Browse archived users" in site administration
    And I should not see "user7"


  @javascript
  Scenario Outline: Manually reactivate user (all checkers)
    Given I log in as "admin"
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I should see "All archived users"
    And I should see "<userid>"
    When I reactivate "<userid>"
    Then I should see "User '<userid>' has been reactivated"
    And I press "Continue"
    And I should see "All archived users"
    And I should not see "<userid>"

    Examples:
      | checker                  | userid  |
      | Suspended Checker        | user9   |
      | Last Login Checker       | user1   |
      | Never Login Checker      | user3   |
      | No active course Checker | user5   |
