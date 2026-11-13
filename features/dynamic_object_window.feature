Feature: Object windows loaded dynamically through the component renderer
  As a user of an East application
  I want a window rendered by the server to show one object over the current page
  So that several objects are open together, each one loaded with the recipe of its route

  Background:
    Given a Symfony application with East UX
    And 7 items in the application

  Scenario: Load a floating object window, hidden and empty until it is opened
    Given I am authenticated as "user"
    When the component renderer is asked for the object window "item-item-2" of the item "Item 2"
    Then the response status must be 200
    And the object window "item-item-2" must be hidden and floating
    And the object window "item-item-2" must be empty
    And the "ObjectWindow" must be re-rendered by the route "/items/view/item-2"

  Scenario: Open a floating object window, its object loaded by the recipe of its path
    Given I am authenticated as "user"
    When the component renderer is asked for the object window "item-item-2" of the item "Item 2"
    And the object window is opened
    Then the response status must be 200
    And the object window "item-item-2" must be shown and floating
    And the object window "item-item-2" must show "Item 2"
    And the "ObjectWindow" must be re-rendered by the route "/items/view/item-2"

  Scenario: Render the object window in the page of its object
    Given I am authenticated as "user"
    When I open the page "/items/view/item-2"
    Then the response status must be 200
    And the object window "item-item-2" must be shown in the page
    And the object window "item-item-2" must show "Item 2"
    And the "ObjectWindow" must be re-rendered by the route "/items/view/item-2"

  Scenario: The access rules of the path of an object window apply when it is opened
    Given I am authenticated as "user"
    When the component renderer is asked for the object window "item-item-2" of the path "/admin/items/new"
    Then the response status must be 200
    When the object window is opened
    Then the response status must be 403

  Scenario: Refuse an object window which does not float
    Given I am authenticated as "user"
    When the component renderer is asked for the object window "item-item-2" of the item "Item 2" without the prop "floating"
    Then the response status must be 400

  Scenario Outline: Refuse a prop the validator of the ObjectWindow does not accept
    Given I am authenticated as "user"
    When the component renderer is asked for the object window "item-item-2" of the item "Item 2" with the prop "<prop>" set to "<value>"
    Then the response status must be 400

    Examples:
      | prop             | value       |
      | floating         | false       |
      | objectInstance   | item-2      |
      | closeRedirectUrl | /items/     |
      | contentComponent | ListWindow  |
      | contentComponent | UnknownView |

  Scenario: Refuse a null prop of the ObjectWindow, which its property does not accept
    Given I am authenticated as "user"
    When the component renderer receives the body '{"component": "ObjectWindow", "props": {"windowId": "item-item-2", "originalPath": "/items/view/item-2", "contentComponent": "ItemView", "floating": true, "title": null}}'
    Then the response status must be 400

  Scenario: The component renderer requires an authenticated user for an object window
    When the component renderer is asked for the object window "item-item-2" of the item "Item 2"
    Then the response status must be 401
