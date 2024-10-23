@tool @tool_cleanupusers
Feature: Cleanup settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | relativedatesmode | timecreated    | lastaccess | suspended | description            |
      | user1    | Teacher   | Miller1   | 1                 | ## -320 days## | ## -11 days ## | 0     | Last Login             |
      | user2    | Teaching  | Miller2   | 1                 | ## -32 days##  | ## -9 days## | 0       |                        |
      | user3    | Student   | Miller3   | 1                 | ## -15 days##  | 0  | 0                 | Never Login            |
      | user4    | Student   | Miller4   | 1                 | ## -14 days##  | 0  | 0                 |                        |
      | user5    | Student   | Miller5   | 1                 | ## -12 days##  | 0  | 0                 | No active course       |
      | user6    | Student   | Miller6   | 1                 | ## -12 days##  | 0  | 0                 |                        |
      | user7    | Student   | Miller7   | 1                 | ## -12 days##  | 0  | 0                 |                        |
      | user8    | Student   | Miller8   | 1                 | ## -12 days##  | 0  | 0                 |                        |
      | user9    | Student   | Miller9   | 1                 | ## -12 days##  | ## -9 days##  | 1      | Suspended              |
      | user0    | Student   | Miller10   | 1                | ## -15 days##  | 0  | 0                 | neverlogin AND nocouse |

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
  Scenario Outline: Two checkers match (manually archived)
    Given I log in as "admin"

    When I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "<otherchecker>" checker on archiving page
    Then I should see "user0"


    When I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "<checker>" checker on archiving page
    Then I should see "user0"

    When I archive "user0"
    Then I should see "User 'user0' has been archived"

    When I press "Continue"
    And I should see "Users to be archived by '<checker>'"
    And I should not see "user0"

    When I navigate to "Users > Clean up users > Archived users" in site administration
    Then I should see "user0"
    And I should see "<checkershort>" in the "user0" "table_row"

    Examples:
      | checker                  | checkershort        | otherchecker             |
      | Never Login Checker      | neverloginchecker   | No active course Checker |
      | No active course Checker | nocoursechecker     | Never Login Checker      |

  @javascript
  Scenario Outline: Two checkers match (task)
    # check that the first configured checker will suspend the user
    Given the following config values are set as admin:
      | userstatus_plugins_enabled | <config>  |
    And I log in as "admin"

    # precondition: user0 is not archived
    When I navigate to "Users > Clean up users > Archived users" in site administration
    Then I should not see "user0"

    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"

    When I navigate to "Users > Clean up users > Archived users" in site administration
    Then I should see "user0"
    And I should see "<checker>" in the "user0" "table_row"

    Examples:
      | config                                                | checker           |
      | neverloginchecker,nocoursechecker                     | neverloginchecker |
      | nocoursechecker,neverloginchecker,suspendedchecker    | nocoursechecker   |


  @javascript
  Scenario: Run suspend task (long)
    Given I log in as "admin"

    # lastloginchecker
    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "Last Login Checker" checker on archiving page
    And I should see "user1"
    And I should not see "user2"
    And I should not see "user3"
    And I should not see "user4"
    And I should not see "user5"
    And I should not see "user6"
    And I should not see "user7"
    And I should not see "user8"

    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "Never Login Checker" checker on archiving page

    And I should see "user3"
    And I should see "user4"
    And I should not see "user1"
    And I should not see "user2"
    And I should not see "user5"
    And I should not see "user6"
    And I should not see "user7"
    And I should not see "user8"

    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "No active course Checker" checker on archiving page

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

    # And I navigate to "Users > Clean up users > Pending cleanup actions" in site administration

    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "Last Login Checker" checker on archiving page
    And I should see "Nothing to display"

    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "Never Login Checker" checker on archiving page
    And I should see "Nothing to display"

    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "No active course Checker" checker on archiving page

    And I should see "Nothing to display"

  @javascript
  Scenario Outline: Manually suspend user (all checkers)
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "<checker>" checker on archiving page

    And I should see "<userid>"
    When I archive "<userid>"
    Then I should see "User '<userid>' has been archived"

    When I press "Continue"
    # And I pause
    And I should see "Users to be archived by '<checker>'"
    And I should not see "<userid>"

    # And I should see "Nothing to display"

    Examples:
      | checker                  | userid  |
      | Suspended Checker        | user9   |
      | Last Login Checker       | user1   |
      | Never Login Checker      | user3   |
      | No active course Checker | user5   |

  @javascript
  Scenario Outline: Run suspend task (all checkers, short)
    Given I log in as "admin"

    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "<checker>" checker on archiving page
    And I should see "<userid>"

    # run task and check that all tables are empty
    And I run the scheduled task "\tool_cleanupusers\task\archive_user_task"

    And I navigate to "Users > Clean up users > Users to be archived" in site administration
    And I select "<checker>" checker on archiving page
    And I should see "Nothing to display"

    Examples:
      | checker                  | userid  |
      | Suspended Checker        | user9   |
      | Last Login Checker       | user1   |
      | Never Login Checker      | user3   |
      | No active course Checker | user5   |