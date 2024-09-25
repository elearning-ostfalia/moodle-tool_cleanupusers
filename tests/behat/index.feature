@tool @tool_cleanupusers
Feature: Cleanup settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  |  relativedatesmode | timecreated | lastaccess |
      | user1    | Teacher   | Miller1   | 1                  | ## -320 days## | ## -11 days##|
      | user2    | Teaching  | Miller2   | 1                  | ## -32 days## | ## -9 days## |
      | user3    | Student   | Miller3   | 1                  | ## -15 days## |  |
      | user4    | Student   | Miller4   | 1                  | ## -14 days## |  |
      | user5    | Student   | Miller5   | 1                  | ## -12 days## |  |
      | user6    | Student   | Miller6   | 1                  | ## -12 days## |  |
      | user7    | Student   | Miller7   | 1                  | ## -12 days## |  |
      | user8    | Student   | Miller8   | 1                  | ## -12 days## |  |
    And the following "courses" exist:
      | fullname  | shortname  | category  | relativedatesmode  | startdate      | enddate     | visible |
      | Active1   | CA1         | 0         | 1                  | ##-32 days##  |             | 1       |
      | Active2   | CA2         | 0         | 1                  | ##-32 days##  | ##32 days## | 1       |
      | Active3   | CA3         | 0         | 1                  | ## 9 days##   | ##100days## | 1 |
      | Active4   | CA4         | 0         | 1                  | ## 9 days##   |             | 1 |
      | Inactive1 | CI1         | 0         | 1                  | ##-32 days##  | ##-10days## | 1        |
      | Inactive2 | CI2         | 0         | 1                  | ##-32 days##  | ##10days##  | 0        |

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

    And ".fa-eye" "css_element" should be visible
    # User 5 is in first row
    When I click on ".fa-eye" "css_element"
    Then I should see "The Users have been archived"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Not enrolled in active course Checker" in site administration
    And I should not see "user5"

  @javascript
  Scenario: Manually suspend user for Never Login Checker
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Never Login Checker" in site administration
    And I should see "user3"

    Then ".fa-eye" "css_element" should be visible
    # User 3 is in first row
    When I click on ".fa-eye" "css_element"
#    And I click on "Suspend User" "link"

    Then I should see "The Users have been archived"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Never Login Checker" in site administration
    And I should not see "user3"


@javascript
  Scenario: Manually suspend user for Last Login Checker
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Last Login Checker" in site administration
    And I should see "user1"

    Then ".fa-eye" "css_element" should be visible
    When I click on ".fa-eye" "css_element"
#    And I click on "Suspend User" "link"

    Then I should see "The Users have been archived"

    And I navigate to "Users > Clean up users > Manage users who will be archived by Last Login Checker" in site administration
    And I should see "Currently no users will be suspended by the next cronjob for checker Last Login Checker."
