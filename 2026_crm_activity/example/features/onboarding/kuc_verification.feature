Feature: KUC Registry Verification
  As a compliance officer
  I want clients to be verified against the KUC registry
  So that flagged or unknown entities are handled appropriately

  Background:
    Given the "business_standard" onboarding template is loaded

  Scenario: Clean NIP passes verification automatically
    Given a client "Czysta Firma Sp. z o.o." with NIP "5261234567"
    And the client has annual revenue of 500000 PLN
    When the onboarding case is started
    Then the step "check_kuc" should have produced outcome "clean"
    And the step "manual_kuc_review" should not have been executed

  Scenario: Flagged NIP triggers manual compliance review
    Given a client "Podejrzana Sp. z o.o." with NIP "9961234567"
    And the client has annual revenue of 200000 PLN
    When the onboarding case is started
    Then the step "check_kuc" should have produced outcome "flagged"
    And the step "manual_kuc_review" should have been executed
    And the case should be completed with outcome "approved"

  Scenario: NIP not found in registry rejects the case
    Given a client "Ghost Company Ltd." with NIP "0061234567"
    And the client has annual revenue of 100000 PLN
    When the onboarding case is started
    Then the step "check_kuc" should have produced outcome "not_found"
    And the case should be completed with outcome "rejected"
    And the following steps should have been executed in order:
      | step                  |
      | collect_prospect_data |
      | check_kuc             |
