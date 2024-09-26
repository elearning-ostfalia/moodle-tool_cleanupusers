@tool @tool_cleanupusers
Feature: Cleanup settings with large number of users

  Background:
    Given create "50" users
    # Values are set per checker as otherwise only one value
    # with the same first column will be set (bug?)
    And the following config values are set as admin:
      | userstatus_plugins_enabled | timechecker,nocoursechecker,neverloginchecker  | |
      | auth_method | manual  | userstatus_nocoursechecker |
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
  Scenario: Index page
    Given I log in as "admin"
    And I pause
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And simulate that "101" days have passed since the archiving of "user1"
    And I navigate to "Users > Clean up users > Manage users who will be deleted" in site administration
    And I should see "user1"
    And I delete "user1"
    And I should see "User 'user1' has been deleted."
    And I navigate to "Users > Clean up users > Manage users who will be deleted" in site administration
    And I should not see "user1"

  @javascript
  Scenario: Manually reactivate users
    Given I log in as "admin"
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And I navigate to "Users > Clean up users > Browse archived users" in site administration
    And I should see "Archived users"
    And I should see "user1"
    And I should see "user3"
    And I should see "user4"
    And I should see "user5"
    And I should see "user6"
    And I should see "user7"
    And I should see "user8"
    When I reactivate "user7"
    Then I should see "The user has been reactivated"
    And I navigate to "Users > Clean up users > Browse archived users" in site administration
    And I should not see "user7"

#  @javascript
  Scenario: Run task for suspend
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Last Login Checker" in site administration
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

    And I navigate to "Users > Clean up users > Manage users who will be archived by Not enrolled in active course Checker" in site administration
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
