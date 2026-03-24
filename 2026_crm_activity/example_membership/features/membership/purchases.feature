Feature: Purchase Activities
  As a loyalty program member
  I earn points when making purchases
  So that I can later redeem them for rewards

  Scenario: In-store purchase earns immediate points
    Given a member "Jan Kowalski" with id "MBR-001"
    When the member makes an in-store purchase of 250 PLN at store "STORE-WAW-01" with transaction "TXN-123"
    Then the member should have 250 active points
    And the member should have 0 pending points
    And the last activity should be of type "purchase_in_store"

  Scenario: Multiple purchases accumulate points
    Given a member "Anna Nowak" with id "MBR-002"
    When the member makes an in-store purchase of 100 PLN at store "STORE-01" with transaction "TXN-A"
    And the member makes an in-store purchase of 200 PLN at store "STORE-02" with transaction "TXN-B"
    Then the member should have 300 active points
    And the member should have 2 activities recorded
