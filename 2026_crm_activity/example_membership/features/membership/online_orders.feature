Feature: Online Order Activities
  As a loyalty program member
  I earn pending points for online purchases
  That become active when the package is delivered

  Scenario: Online purchase earns pending points with 1.5x multiplier
    Given a member "Jan Kowalski" with id "MBR-010"
    When the member makes an online purchase of 200 PLN with order "ORD-001"
    Then the member should have 0 active points
    And the member should have 300 pending points
    And the last activity should be of type "online_purchase"

  Scenario: Pending points activate after delivery
    Given a member "Jan Kowalski" with id "MBR-011"
    When the member makes an online purchase of 200 PLN with order "ORD-002"
    And the package for order "ORD-002" is delivered
    Then the member should have 300 active points
    And the member should have 0 pending points

  Scenario: Mixed purchases with partial delivery
    Given a member "Anna Nowak" with id "MBR-012"
    When the member makes an in-store purchase of 100 PLN at store "STORE-01" with transaction "TXN-X"
    And the member makes an online purchase of 200 PLN with order "ORD-A"
    And the member makes an online purchase of 100 PLN with order "ORD-B"
    Then the member should have 100 active points
    And the member should have 450 pending points
    When the package for order "ORD-A" is delivered
    Then the member should have 400 active points
    And the member should have 150 pending points
