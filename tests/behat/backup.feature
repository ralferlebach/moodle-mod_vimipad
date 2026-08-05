@mod @mod_vimipad
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

  # Note: the full backup/restore *data* roundtrip — including the grade and its
  # snapshot reference being remapped into the restored course — is covered
  # reliably and thoroughly by the PHPUnit test backup_restore_test. A UI-level
  # backup/restore scenario was tried here but the interactive backup wizard is
  # unreliable under moodle-plugin-ci (the completion "Continue" step races the
  # asynchronous backup), so it is intentionally not asserted through Behat.

  @javascript
  Scenario: Duplicating the activity creates an independent copy
    When I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I duplicate "Energy map" activity
    Then I should see "Energy map (copy)"
