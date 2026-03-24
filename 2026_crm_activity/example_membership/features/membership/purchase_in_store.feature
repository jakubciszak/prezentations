Feature: In-Store Purchase Loyalty Activity
  As a loyalty program manager
  I want to process in-store purchases through the points system
  So that members earn points and rewards for their purchases

  Background:
    Given the "purchase_in_store" activity template is loaded

  # === Happy path ===

  Scenario: Standard purchase earns points with no tier change
    Given a member "MBR-001" with a purchase of 150 PLN at store "STORE-WAW-01"
    And the member has 3000 total points on "bronze" tier
    When the loyalty activity is started
    Then the case should be completed with outcome "points_awarded"
    And the following steps should have been executed in order:
      | step                   |
      | validate_transaction   |
      | calculate_points       |
      | evaluate_tier          |
      | assign_reward          |

  # === High value purchase with reward ===

  Scenario: High value purchase earns reward voucher
    Given a member "MBR-002" with a purchase of 350 PLN at store "STORE-KRK-01"
    And the member has 8000 total points on "silver" tier
    When the loyalty activity is started
    Then the case should be completed with outcome "points_awarded"
    And the step "assign_reward" should have been executed

  # === Tier upgrade ===

  Scenario: Purchase triggers tier upgrade when threshold reached
    Given a member "MBR-003" with a purchase of 500 PLN at store "STORE-GDA-01"
    And the member has 10500 total points on "silver" tier
    When the loyalty activity is started
    Then the case should be completed with outcome "points_awarded"
    And the step "process_tier_upgrade" should have been executed

  # === Fraud suspected ===

  Scenario: Suspicious high-value transaction goes through manual review
    Given a member "MBR-004" with a purchase of 8000 PLN at store "STORE-WAW-02"
    And the member has 1000 total points on "bronze" tier
    When the loyalty activity is started
    Then the case should be completed with outcome "points_awarded"
    And the step "manual_transaction_review" should have been executed

  # === Invalid transaction ===

  Scenario: Invalid transaction is rejected immediately
    Given a member "MBR-005" with a purchase of 0 PLN at store "STORE-WAW-01"
    And the member has 500 total points on "bronze" tier
    When the loyalty activity is started
    Then the case should be completed with outcome "rejected"
    And the step "calculate_points" should not have been executed
