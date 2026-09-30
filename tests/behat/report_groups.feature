@mod @mod_vocabmastery
Feature: The vocabulary report respects separate groups
  In order to protect learners' privacy
  As a non-editing teacher
  I can only see learners in my own groups

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tina      | Teacher  |
      | teacher2 | Theo      | Teacher  |
      | student1 | Anna      | Alpha    |
      | student2 | Bert      | Beta     |
    And the following "courses" exist:
      | fullname | shortname | groupmode |
      | German 1 | G1        | 1         |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | G1     | teacher |
      | teacher2 | G1     | teacher |
      | student1 | G1     | student |
      | student2 | G1     | student |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | G1     | GA       |
      | Group B | G1     | GB       |
    And the following "group members" exist:
      | user     | group |
      | teacher1 | GA    |
      | student1 | GA    |
      | student2 | GB    |
    And the following "activities" exist:
      | activity     | name       | course | idnumber | groupmode |
      | vocabmastery | Vocabulary | G1     | vocab1   | 1         |
    And the following "mod_vocabmastery > words" exist:
      | activity | topic     | prompt | answer |
      | vocab1   | Lektion 1 | Vater  | der    |

  Scenario: A teacher in one group only sees that group
    When I am on the "vocab1" "mod_vocabmastery > Report" page logged in as "teacher1"
    Then I should see "Anna Alpha"
    And I should not see "Bert Beta"

  Scenario: A teacher in no group sees nobody
    When I am on the "vocab1" "mod_vocabmastery > Report" page logged in as "teacher2"
    Then I should not see "Anna Alpha"
    And I should not see "Bert Beta"
    And I should see "No learners to report on."
