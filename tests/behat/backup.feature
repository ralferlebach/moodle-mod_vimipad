@mod @mod_vimipad @javascript
Feature: ViMi Pad survives course backup and restore
  In order to move and copy courses without losing learner work
  As a teacher
  I need submitted maps to come back intact after a backup and restore

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam       | Student  | student1@test.com |
      | teacher1 | Tay       | Teacher  | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name       | course | idnumber | defaultprofile |
      | vimipad  | Energy map | C1     | vimipad1 | conceptmap     |
    And the following "mod_vimipad > submissions" exist:
      | vimipad  | user     | label  |
      | vimipad1 | student1 | Energy |

  Scenario: A restored course keeps the submitted map and its grade access
    When I am on the "Energy map" "vimipad activity" page logged in as teacher1
    And I follow "Grading"
    And I follow "View and grade"
    And I set the field "Grade (out of 100)" to "70"
    And I press "Save grade"
    Then I should see "Grade saved."
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | vimipad_backup.mbz |
    And I restore "vimipad_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course 1 restored |
    Then I should see "Course 1 restored"
    And I should see "Energy map"
    When I follow "Energy map"
    And I follow "Grading"
    Then I should see "Sam Student"
    And I should see "Submitted"

  Scenario: Duplicating the activity creates an independent copy
    When I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I duplicate "Energy map" activity
    Then I should see "Energy map (copy)"
