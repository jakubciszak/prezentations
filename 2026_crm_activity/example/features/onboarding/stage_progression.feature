Feature: Stage Progression
  As an onboarding manager
  I want to track which stages have been completed
  So that I can see the progress of each case

  Background:
    Given the "business_standard" onboarding template is loaded

  Scenario: All stages are completed after successful onboarding
    Given a client "Test Firma Sp. z o.o." with NIP "5261234567"
    And the client has annual revenue of 500000 PLN
    When the onboarding case is started
    Then all stages should be completed
    And the event log should contain "CASE STARTED"
    And the event log should contain "CASE FINISHED"

  Scenario: Only early stages complete when case is rejected at KUC
    Given a client "Ghost Company Ltd." with NIP "0061234567"
    And the client has annual revenue of 100000 PLN
    When the onboarding case is started
    Then the case should be completed with outcome "rejected"
    And the stage "Prospect Intake" should be completed
    And the stage "Document Collection" should not be completed
    And the stage "Finalization" should not be completed
