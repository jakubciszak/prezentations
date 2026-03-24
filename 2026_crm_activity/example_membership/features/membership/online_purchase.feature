Feature: Online Purchase Loyalty Activity
  As a loyalty program manager
  I want to process online purchases with bonus multiplier
  So that members get extra rewards for shopping online

  Background:
    Given the "online_purchase" activity template is loaded

  Scenario: Online purchase earns bonus points
    Given a member "MBR-010" with an online purchase of 200 PLN
    And the member has 5000 total points on "silver" tier
    When the loyalty activity is started
    Then the case should be completed with outcome "points_awarded"
    And the following steps should have been executed in order:
      | step                   |
      | validate_transaction   |
      | calculate_points       |
      | evaluate_tier          |

  Scenario: Online purchase triggers tier upgrade
    Given a member "MBR-011" with an online purchase of 300 PLN
    And the member has 12000 total points on "gold" tier
    When the loyalty activity is started
    Then the case should be completed with outcome "tier_upgraded"
    And the step "auto_upgrade" should have been executed
