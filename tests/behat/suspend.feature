@tool @tool_cleanupusers
Feature: Cleanup settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | relativedatesmode | timecreated    | lastaccess |
      | user1    | Teacher   | Miller1   | 1                 | ## -320 days## | ## -11 days ## |
      | user2    | Teaching  | Miller2   | 1                 | ## -32 days##  | ## -9 days## |
      | user3    | Student   | Miller3   | 1                 | ## -15 days##  | 0  |
      | user4    | Student   | Miller4   | 1                 | ## -14 days##  | 0  |
      | user5    | Student   | Miller5   | 1                 | ## -12 days##  | 0  |
      | user6    | Student   | Miller6   | 1                 | ## -12 days##  | 0  |
      | user7    | Student   | Miller7   | 1                 | ## -12 days##  | 0  |
      | user8    | Student   | Miller8   | 1                 | ## -12 days##  | 0  |
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
      | userstatus_plugins_enabled | timechecker,nocoursechecker,neverloginchecker  | |
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

  @javascript
  Scenario: Manually reactivate user (preselected by nocoursechecker)
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And I navigate to "Users > Clean up users > Manage archived users" in site administration
    And I pause
    And I set the field with xpath "//select[@name='action']" to "users to be reactivated by"
    And I set the field with xpath "//select[@name='subplugin']" to "nocoursechecker"
    And I should see "user1"
    And I delete "user1"
    And I should see "User 'user1' has been deleted."
    And I press "Continue"
    And I should see "Archived users"
    And I should see "users to be deleted by"
    And I should see "timechecker"
    And I should not see "user1"
    And I set the field with xpath "//select[@name='action']" to "all archived users"
    And I should not see "user1"

  @javascript
  Scenario: Manually delete user (preselected by timechecker)
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And simulate that "101" days have passed since archiving of "user1"
    And I navigate to "Users > Clean up users > Manage archived users" in site administration
    And I set the field with xpath "//select[@name='action']" to "users to be deleted by"
    And I set the field with xpath "//select[@name='subplugin']" to "timechecker"
    And I should see "user1"
    And I delete "user1"
    And I should see "User 'user1' has been deleted."
    And I press "Continue"
    And I should see "Archived users"
    And I should see "users to be deleted by"
    And I should see "timechecker"
    And I should not see "user1"
    And I set the field with xpath "//select[@name='action']" to "all archived users"
    And I should not see "user1"

  @javascript
  Scenario: Manually reactivate user (all archived users filter)
    Given I log in as "admin"
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And I navigate to "Users > Clean up users > Manage archived users" in site administration
    And I should see "Archived users"
    And I should see "all archived users"
    And I should see "user1"
    And I should see "user3"
    And I should see "user4"
    And I should see "user5"
    And I should see "user6"
    And I should see "user7"
    And I should see "user8"
    When I reactivate "user7"
    Then I should see "The user has been reactivated"
    And I press "Continue"
    And I should see "Archived users"
    And I should see "all archived users"
    # And I navigate to "Users > Clean up users > Browse archived users" in site administration
    And I should not see "user7"

  @javascript
  Scenario: Run task for suspend
    Given I log in as "admin"

    # timechecker
    And I navigate to "Users > Clean up users > Manage users to be archived" in site administration
    And I set the field "Please select a subplugin" to "timechecker"
    And I pause
    And I should see "user1"
    And I should not see "user2"
    And I should not see "user3"
    And I should not see "user4"
    And I should not see "user5"
    And I should not see "user6"
    And I should not see "user7"
    And I should not see "user8"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Never Login Checker" in site administration
    And I should see "user3"
    And I should see "user4"
    And I should not see "user1"
    And I should not see "user2"
    And I should not see "user5"
    And I should not see "user6"
    And I should not see "user7"
    And I should not see "user8"

    And I navigate to "Users > Clean up users > Manage users to be archived" in site administration
    And I set the field "Please select a subplugin" to "nocoursechecker"
    And I should see "user5"
    And I should see "user6"
    And I should see "user7"
    And I should see "user8"
    And I should not see "user1"
    And I should not see "user2"
    And I should not see "user3"
    And I should not see "user4"

    # run task and check that all tables are empty
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"

    And I navigate to "Users > Clean up users > General settings" in site administration

    And I navigate to "Users > Clean up users > Manage users who will be archived by Last Login Checker" in site administration
    And I should see "Currently no users will be suspended by the next cronjob for checker Last Login Checker."


    And I navigate to "Users > Clean up users > Manage users who will be archived by Never Login Checker" in site administration
    And I should see "Currently no users will be suspended by the next cronjob for checker Never Login Checker."

    And I navigate to "Users > Clean up users > Manage users who will be archived by Not enrolled in active course Checker" in site administration
    And I should see "Currently no users will be suspended by the next cronjob for checker Not enrolled in active course Checker."

  @javascript
  Scenario: Manually suspend user for Not enrolled in active course Checker
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Not enrolled in active course Checker" in site administration
    And I should see "user5"
    When I archive "user5"
    Then I should see "User 'user5' has been archived"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Not enrolled in active course Checker" in site administration
    And I should not see "user5"

  @javascript
  Scenario: Manually suspend user for Never Login Checker
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Never Login Checker" in site administration
    And I should see "user3"

    When I archive "user3"
    Then I should see "User 'user3' has been archived"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Never Login Checker" in site administration
    And I should not see "user3"


@javascript
  Scenario: Manually suspend user for Last Login Checker
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Last Login Checker" in site administration
    And I should see "user1"

  When I archive "user1"
  Then I should see "User 'user1' has been archived"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Last Login Checker" in site administration
    And I should see "Currently no users will be suspended by the next cronjob for checker Last Login Checker."
