@mod @mod_vimipad
Feature: Teachers grade submitted ViMi Pad snapshots
  In order to assess knowledge maps
  As a teacher
  I need to view submitted snapshots and record grades

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

  Scenario: A teacher sees the list of submissions
    When I am on the "Energy map" "vimipad activity" page logged in as teacher1
    Then I should see "Submissions"
    And I should see "Sam Student"
    And I should see "Submitted"

  Scenario: A teacher records a grade for a submission
    Given I am on the "Energy map" "vimipad activity" page logged in as teacher1
    When I follow "View and grade"
    Then I should see "View and grade snapshot"
    And I should see "Energy"
    When I set the field "Grade (out of 100)" to "80"
    And I set the field "Feedback" to "Solid start, expand the links."
    And I press "Save grade"
    Then I should see "Grade saved."

  Scenario: A teacher adds an annotation to a snapshot
    Given I am on the "Energy map" "vimipad activity" page logged in as teacher1
    And I follow "View and grade"
    When I set the field "Annotation" to "Consider adding a root concept."
    And I press "Add"
    Then I should see "Annotation added."
    And I should see "Consider adding a root concept."

  Scenario: A teacher opens the edit-activity report
    Given I am on the "Energy map" "vimipad activity" page logged in as teacher1
    When I follow "Edit-activity report"
    Then I should see "Edit activity"
    And I should see "Sam Student"

  Scenario: A student cannot see the grading interface
    When I am on the "Energy map" "vimipad activity" page logged in as student1
    Then I should not see "Submissions"
