Feature: Tier Progression
  As a loyalty program manager
  I want to track tier changes through activity processing
  So that members are automatically upgraded when they reach thresholds

  Background:
    Given the "purchase_in_store" activity template is loaded

  Scenario: All stages complete during standard purchase flow
    Given a member "MBR-001" with a purchase of 150 PLN at store "STORE-WAW-01"
    And the member has 3000 total points on "bronze" tier
    When the loyalty activity is started
    Then all stages should be completed

  Scenario: Tier upgrade processes through additional upgrade step
    Given a member "MBR-003" with a purchase of 500 PLN at store "STORE-GDA-01"
    And the member has 10500 total points on "silver" tier
    When the loyalty activity is started
    Then the step "evaluate_tier" should have produced outcome "tier_upgrade"
    And the step "process_tier_upgrade" should have been executed
    And all stages should be completed
