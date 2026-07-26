@mod @mod_vimipad @javascript
Feature: Learners build knowledge maps with the ViMi Pad editor
  In order to construct knowledge visually
  As a student
  I need an interactive editor that loads and lets me add concepts

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam       | Student  | student1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name       | course | idnumber | defaultprofile |
      | vimipad  | Energy map | C1     | vimipad1 | conceptmap     |

  Scenario: The editor mounts and replaces the loading placeholder
    When I am on the "Energy map" "vimipad activity" page logged in as student1
    And I wait until "Add concept" "fieldset" exists
    Then I should not see "Loading the ViMi Pad editor"
    And I should see "Canvas"
    And I should see "List"

  Scenario: A student adds a concept to the map
    Given I am on the "Energy map" "vimipad activity" page logged in as student1
    And I wait until "Add concept" "fieldset" exists
    When I set the field "Concept label" to "Energy"
    And I click on "Add" "button" in the "Add concept" "fieldset"
    And I click on "List" "button"
    Then I should see "Energy"
