Feature: Reward Redemption
  As a loyalty program member
  I want to exchange my points for rewards
  So that I get value from my loyalty

  Scenario: Member redeems a coupon with sufficient points
    Given a member "Jan Kowalski" with id "MBR-030"
    And the member has earned 2000 points from purchases
    When the member redeems reward "RWD-10PCT"
    Then the member should have 1000 active points
    And the member should have 1 redemption
    And the last activity should be of type "reward_redemption"

  Scenario: Redemption fails when insufficient points
    Given a member "Anna Nowak" with id "MBR-031"
    And the member has earned 200 points from purchases
    Then redeeming reward "RWD-10PCT" should fail with insufficient points
    And the member should have 200 active points
    And the member should have 0 redemptions
