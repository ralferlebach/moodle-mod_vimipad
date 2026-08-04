@mod @mod_vimipad @accessibility @javascript
Feature: ViMi Pad pages meet accessibility standards
  In order to be usable with assistive technology
  As any user
  I need the activity pages to pass automated accessibility checks

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

  Scenario: The learner editor page meets accessibility standards
    When I am on the "Energy map" "vimipad activity" page logged in as student1
    And I wait until "Add concept" "fieldset" exists
    Then the page should meet accessibility standards

  Scenario: The teacher grading page meets accessibility standards
    When I am on the "Energy map" "vimipad activity" page logged in as teacher1
    And I follow "Grading"
    Then the page should meet accessibility standards

  Scenario: The submission grading view meets accessibility standards
    When I am on the "Energy map" "vimipad activity" page logged in as teacher1
    And I follow "Grading"
    And I follow "View and grade"
    Then the page should meet accessibility standards
