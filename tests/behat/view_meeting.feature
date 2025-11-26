@mod @mod_zoom
Feature: View a meeting

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
      | student1 | Sam1      | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity | zoom                     |
      | course   | C1                       |
      | idnumber | 00001                    |
      | name     | Meeting 1        |
      | intro    | Test meeting description |
      | section  | 1                        |
      | grade    | 100                      |

  @javascript
  Scenario: As a student, I should be able to view a Zoom meeting's details
    When I am on the "Meeting 1" "mod_zoom > View" page logged in as "student1"
    Then I should see "Start Time"
