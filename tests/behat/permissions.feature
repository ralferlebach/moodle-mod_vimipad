@mod @mod_vimipad
Feature: ViMi Pad respects roles and capabilities
  In order to keep learner work and grading private
  As a teacher or administrator
  I need the activity to expose only what each role is allowed to do

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
    And the following "activities" exist:
      | activity | name       | course | idnumber | defaultprofile |
      | vimipad  | Energy map | C1     | vimipad1 | conceptmap     |
    And the following "mod_vimipad > submissions" exist:
      | vimipad  | user     | label  |
      | vimipad1 | student1 | Energy |

  Scenario: A teacher can reach the grading interface
    When I am on the "Energy map" "vimipad activity" page logged in as teacher1
    Then I should see "Grading"

  Scenario: A student cannot reach the grading interface
    When I am on the "Energy map" "vimipad activity" page logged in as student1
    Then I should not see "Grading"
    And I should not see "Submissions"

  Scenario: A second student cannot see another learner's submission
    When I am on the "Energy map" "vimipad activity" page logged in as student2
    Then I should not see "Grading"
    And I should not see "Sam Student"

  Scenario: Removing the grade capability hides grading from a teacher
    Given the following "permission overrides" exist:
      | capability       | permission | role           | contextlevel | reference |
      | mod/vimipad:grade | Prohibit  | editingteacher | Course       | C1        |
    When I am on the "Energy map" "vimipad activity" page logged in as teacher1
    Then I should not see "Grading"

  Scenario: Removing the view capability blocks access to the activity
    Given the following "permission overrides" exist:
      | capability       | permission | role    | contextlevel | reference |
      | mod/vimipad:view | Prohibit   | student | Course       | C1        |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should not see "Energy map"
