Feature: Simplified Partner Onboarding
  As an onboarding manager
  I want pre-verified partners to go through a shorter onboarding
  So that trusted clients are onboarded faster

  Background:
    Given the "business_simplified" onboarding template is loaded

  Scenario: Pre-verified partner completes simplified flow
    Given a client "Partner Fintech Sp. z o.o." with NIP "1234567890"
    And the client has annual revenue of 300000 PLN
    And the client has partner referral code "REF-2026-001"
    When the onboarding case is started
    Then the case should be completed with outcome "approved"
    And the following steps should have been executed in order:
      | step                      |
      | collect_prospect_data     |
      | quick_risk_check          |
      | collect_minimal_documents |
      | auto_approve              |
    And the step "check_kuc" should not have been executed
    And the step "calculate_risk" should not have been executed
