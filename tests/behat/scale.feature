@tool @tool_cleanupusers
Feature: Cleanup settings with large number of users

  Background:
    # create 'many' users
    # * no user has ever logged in => archived by neverloginchecker
    # * no user is enrolled in course => archived by nocoursechecker
    # => nocoursechecker comes first => all users will be suspeneded by nocoursechecker
    Given create "500" users
    # Values are set per checker as otherwise only one value
    # with the same first column will be set (bug?)
    And the following config values are set as admin:
      | userstatus_plugins_enabled | lastloginchecker,nocoursechecker,neverloginchecker  | |
      | auth_method | manual  | userstatus_nocoursechecker |
      | deletetime | 15  | userstatus_nocoursechecker |
    And the following config values are set as admin:
      | auth_method | manual  | userstatus_lastloginchecker |
      | suspendtime | 10  | userstatus_lastloginchecker |
      | deletetime | 100  | userstatus_lastloginchecker |
    And the following config values are set as admin:
      | suspendtime | 14  | userstatus_neverloginchecker |
      | deletetime | 200  | userstatus_neverloginchecker |
      | auth_method | manual  | userstatus_neverloginchecker |

  @javascript
  Scenario: Large: manually delete user1
    Given I log in as "admin"
    # And I navigate to "Users > Clean up users > General settings" in site administration
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And simulate that "101" days have passed since archiving of "user1"
    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be deleted" archive page
    And I select "No active course Checker" checker on archive page
    And I should see "user1"
    And I delete "user1"
    When I press "Delete"
    Then I should see "User 'user1' has been deleted."
    And I press "Continue"
    And I should see "Users to be deleted"
    And I should see "No active course Checker"
    And I should not see "user1"

  @javascript
  Scenario: Large: manually delete with user filter
    Given I log in as "admin"
    # archive all users ready for archive
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"
    And simulate that "101" days have passed since archiving from "user1" to "user400"
    And I navigate to "Users > Clean up users > Archived users" in site administration
    And I navigate to "Users to be deleted" archive page
    And I select "No active course Checker" checker on archive page
    And I press "Show more..."
    And I set the field with xpath "//input[@name='username']" to "user8"
    And I set the field with xpath "//select[@name='username_op']" to "is equal to"
    And I press "Add filter"
    And I should see "user8"
    And I should see "Users to be deleted"
    And I should see "No active course Checker"
    And I delete "user8"
    When I press "Delete"
    Then I should see "User 'user8' has been deleted."
    And I press "Continue"
    # check correct redirect
    And I should see "Users to be deleted"
    And I should see "No active course Checker"
    And I should see "Nothing to display"
