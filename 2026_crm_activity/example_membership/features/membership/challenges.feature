Feature: Challenge Activities
  As a loyalty program member
  I earn bonus points when completing challenges
  To accelerate my points balance

  Scenario: Completing a challenge earns bonus points
    Given a member "Jan Kowalski" with id "MBR-020"
    When the member completes challenge "SUMMER-2026" earning 500 bonus points
    Then the member should have 500 active points
    And the last activity should be of type "challenge_completed"

  Scenario: Birthday bonus adds points
    Given a member "Anna Nowak" with id "MBR-021"
    When the member receives a birthday bonus of 100 points
    Then the member should have 100 active points
    And the last activity should be of type "birthday_bonus"
