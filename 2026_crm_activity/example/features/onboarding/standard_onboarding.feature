Feature: Standard Business Onboarding
  As an onboarding manager
  I want to process new business clients through verification stages
  So that only eligible clients are approved

  Background:
    Given the "business_standard" onboarding template is loaded

  # === Happy path ===

  Scenario: Low risk client with clean NIP is approved
    Given a client "Acme Fintech Sp. z o.o." with NIP "5261234567"
    And the client has annual revenue of 500000 PLN
    When the onboarding case is started
    Then the case should be completed with outcome "approved"
    And the following steps should have been executed in order:
      | step                    |
      | collect_prospect_data   |
      | check_kuc               |
      | calculate_risk          |
      | collect_basic_documents |
      | final_approval          |

  # === Medium risk ===

  Scenario: Medium risk client requires extended document collection
    Given a client "Średnia Korporacja S.A." with NIP "7891234567"
    And the client has annual revenue of 5000000 PLN
    When the onboarding case is started
    Then the case should be completed with outcome "approved"
    And the step "collect_extended_documents" should have been executed
    And the step "collect_basic_documents" should not have been executed

  # === High risk ===

  Scenario: High risk client is rejected immediately after risk calculation
    Given a client "Duża Korporacja S.A." with NIP "1234567890"
    And the client has annual revenue of 15000000 PLN
    When the onboarding case is started
    Then the case should be completed with outcome "rejected"
    And the step "collect_basic_documents" should not have been executed
    And the step "collect_extended_documents" should not have been executed
