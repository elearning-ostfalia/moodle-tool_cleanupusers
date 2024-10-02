@tool @tool_cleanupusers
Feature: Cleanup settings with large number of users

  Background:
    Given create "5000" users
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
  Scenario: Large: Index page
    Given I log in as "admin"
    # And I pause
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

  @javascript
  Scenario: Large: manually delete users
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And simulate that "101" days have passed since archiving from "user1" to "user400"
    And I navigate to "Users > Clean up users > Manage archived users" in site administration
    And I set the field with xpath "//select[@name='action']" to "users to be deleted by"
    And I set the field with xpath "//select[@name='subplugin']" to "timechecker"
    And I should see "user8"
    And I delete "user8"
    And I should see "User 'user8' has been deleted."
    And I press "Continue"
    # check correct redirect
    And I should see "Users to be deleted"
    And I should not see "user8"
    And I should see "user2"
