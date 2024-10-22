@tool @tool_cleanupusers
Feature: Cleanup settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | relativedatesmode | timecreated    | lastaccess | suspended |
      | user1    | Teacher   | Miller1   | 1                 | ## -320 days## | ## -11 days ## | 0     |
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
      | user1    | CA1     | editingteacher |
      | user2    | CA2     | student |
      | user3    | CA3     | editingteacher |
      | user4    | CA4     | student |
      | user5    | CI1     | editingteacher |
      | user6    | CI2     | student |

    # Values are set per checker as otherwise only one value
    # with the same first column will be set (bug?)
    And the following config values are set as admin:
      | userstatus_plugins_enabled | timechecker,nocoursechecker,neverloginchecker,suspendedchecker  | |
      | auth_method | manual  | userstatus_nocoursechecker |
#      | suspendtime | 20  | userstatus_nocoursechecker |
      | deletetime | 15  | userstatus_nocoursechecker |
    And the following config values are set as admin:
      | auth_method | manual  | userstatus_timechecker |
      | suspendtime | 10  | userstatus_timechecker |
      | deletetime | 100  | userstatus_timechecker |
    And the following config values are set as admin:
      | suspendtime | 14  | userstatus_neverloginchecker |
      | deletetime | 200  | userstatus_neverloginchecker |
      | auth_method | manual  | userstatus_neverloginchecker |
    And the following config values are set as admin:
      | auth_method | manual  | userstatus_suspendedchecker |
      | suspendtime | 0  | userstatus_suspendedchecker |
      | deletetime | 100  | userstatus_suspendedchecker |

 # TODO
  # Unterscheidung der Checker bei der Filterung

  @javascript
  Scenario: Manually delete user (preselected by timechecker)
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
    And I should see "Teacher Miller1"

    When I click on "Cancel" "button" in the "Completely delete user" "dialogue"
    Then I should see "user1"

    When I delete "user1"
    Then "Completely delete user" "dialogue" should exist
    And I should see "Teacher Miller1"

    When I press "Delete"
    Then I should see "User 'user1' has been deleted."

    When I press "Continue"
    Then I should see "Users to be deleted"
    And I should see "Last Login Checker"
    And I should not see "user1"

    When I navigate to "All archived users" archive page
    Then I should not see "user1"

