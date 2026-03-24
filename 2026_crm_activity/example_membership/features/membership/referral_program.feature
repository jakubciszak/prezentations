Feature: Referral Program Loyalty Activity
  As a loyalty program manager
  I want to reward members for successful referrals
  So that the membership base grows through word-of-mouth

  Background:
    Given the "referral_program" activity template is loaded

  Scenario: Valid referral awards bonus points to both members
    Given a referral from member "MBR-020" for new member "MBR-021" with code "REF-ABC123"
    When the loyalty activity is started
    Then the case should be completed with outcome "points_awarded"
    And the following steps should have been executed in order:
      | step                   |
      | validate_referral      |
      | calculate_bonus        |
      | notify_members         |

  Scenario: Duplicate referral code is rejected
    Given a referral from member "MBR-020" for new member "MBR-022" with code "DUP-EXISTING"
    When the loyalty activity is started
    Then the case should be completed with outcome "rejected"
    And the step "calculate_bonus" should not have been executed

  Scenario: Invalid referral code is rejected
    Given a referral from member "MBR-030" for new member "MBR-031" with code "INV-EXPIRED"
    When the loyalty activity is started
    Then the case should be completed with outcome "rejected"
