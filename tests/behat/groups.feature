@mod @mod_vimipad
Feature: ViMi Pad honours Moodle group modes
  In order to run maps per group
  As a teacher
  I need the activity to show the group selector and to validate group settings

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam       | Student  | student1@test.com |
      | student2 | Sky       | Second   | student2@test.com |
      | teacher1 | Tay       | Teacher  | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | GA       |
      | Group B | C1     | GB       |
    And the following "group members" exist:
      | user     | group |
      | student1 | GA    |
      | student2 | GB    |

  Scenario: A teacher sees the group selector under separate groups
    Given the following "activities" exist:
      | activity | name       | course | idnumber | defaultprofile | collaborationmode | groupmode |
      | vimipad  | Team map   | C1     | vimipad1 | conceptmap     | 1                 | 1         |
    When I am on the "Team map" "vimipad activity" page logged in as teacher1
    Then I should see "Group A"

  Scenario: With no groups there is no group selector for a student
    Given the following "activities" exist:
      | activity | name       | course | idnumber | defaultprofile | groupmode |
      | vimipad  | Solo map   | C1     | vimipad2 | conceptmap     | 0         |
    When I am on the "Solo map" "vimipad activity" page logged in as student1
    Then I should not see "Separate groups"

  Scenario: A group map requires a Moodle group mode
    When I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I add a "vimipad" activity to course "Course 1" section "1" and I fill the form with:
      | Activity name    | Group knowledge map |
      | Working mode     | Group work          |
      | Group mode       | No groups           |
    Then I should see "A group map requires a group mode"
