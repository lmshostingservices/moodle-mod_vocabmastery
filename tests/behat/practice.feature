@mod @mod_vocabmastery
Feature: Learners practise vocabulary and see their results
  In order to learn words
  As a learner
  I need to practise a topic and see how I did

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Learner  |
    And the following "courses" exist:
      | fullname | shortname |
      | German 1 | G1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | G1     | student |
    And the following "activities" exist:
      | activity     | name       | course | idnumber | unlockby |
      | vocabmastery | Vocabulary | G1     | vocab1   | practice |
    And the following "mod_vocabmastery > words" exist:
      | activity | topic      | prompt | answer |
      | vocab1   | Lektion 1  | Vater  | der    |
      | vocab1   | Lektion 1  | Hund   | der    |
      | vocab1   | Lektion 1  | Tisch  | der    |

  Scenario: A learner opens the activity and sees the topic
    When I am on the "Vocabulary" "vocabmastery activity" page logged in as "student1"
    Then I should see "Lektion 1"

  @javascript @accessibility
  Scenario: A learner practises a topic and sees the results screen
    Given I am on the "Vocabulary" "vocabmastery activity" page logged in as "student1"
    And I click on "Lektion 1" "link"
    And the page should meet accessibility standards
    When I click on "[data-mode='practice']" "css_element"
    And I answer "der" to every vocabulary question
    Then I should see "Excellent!"
    And I should see "Review answers"
    And the page should meet accessibility standards with "wcag131, wcag141, wcag412" extra tests

  @javascript
  Scenario: Answers can be hidden after the mastery check
    Given the following "activities" exist:
      | activity     | name        | course | idnumber | testreview |
      | vocabmastery | Check only  | G1     | vocab2   | hide       |
    And the following "mod_vocabmastery > words" exist:
      | activity | topic     | prompt | answer |
      | vocab2   | Lektion 1 | Vater  | der    |
      | vocab2   | Lektion 1 | Hund   | der    |
    And I am on the "Check only" "vocabmastery activity" page logged in as "student1"
    And I click on "Lektion 1" "link"
    When I click on "[data-mode='test']" "css_element"
    And I answer "die" to every vocabulary question
    Then I should see "Not yet"
    And I should see "Your teacher has hidden the answers for this check."
    And I should see "Practise this topic"
    And I should not see "Review answers"
    And "Vater" "text" should not exist in the ".vm-res" "css_element"
