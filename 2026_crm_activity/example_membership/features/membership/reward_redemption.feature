Feature: Reward Redemption
  As a loyalty program member
  I want to exchange my points for rewards from the catalog
  So that I can benefit from my accumulated loyalty points

  Background:
    Given the "reward_redemption" activity template is loaded

  Scenario: Member redeems a coupon with sufficient balance
    Given a member "MBR-100" with 5000 points balance
    And the member wants to redeem reward "RWD-10PCT" costing 1000 points
    When the loyalty activity is started
    Then the case should be completed with outcome "reward_redeemed"
    And the following steps should have been executed in order:
      | step             |
      | validate_reward  |
      | check_balance    |
      | debit_points     |
      | issue_reward     |
    And the member "MBR-100" should have 4000 points remaining

  Scenario: Redemption rejected when insufficient balance
    Given a member "MBR-101" with 100 points balance
    And the member wants to redeem reward "RWD-VIP" costing 15000 points
    When the loyalty activity is started
    Then the case should be completed with outcome "rejected"
    And the step "debit_points" should not have been executed
    And the member "MBR-101" should have 100 points remaining

  Scenario: Redemption rejected for nonexistent reward
    Given a member "MBR-102" with 5000 points balance
    And the member wants to redeem reward "RWD-NONEXISTENT" costing 100 points
    When the loyalty activity is started
    Then the case should be completed with outcome "rejected"
    And the step "check_balance" should not have been executed

  Scenario: Redemption rejected for inactive reward
    Given a member "MBR-103" with 5000 points balance
    And the member wants to redeem reward "RWD-INACTIVE" costing 100 points
    When the loyalty activity is started
    Then the case should be completed with outcome "rejected"
    And the step "check_balance" should not have been executed
