Feature: Form windows loaded dynamically through the component renderer
  As a user of a list window
  I want the "New" and "Edit" buttons to open a form window rendered by the server
  So that the window carries the props signed by Symfony UX

  Background:
    Given a Symfony application with East UX
    And 3 items in the application

  Scenario: Load and open the form window of a new item
    Given I am authenticated as "user"
    When the component renderer is asked for a "FormWindow" with the props:
      | windowId | new-item   |
      | formPath | /items/new |
      | formName | ItemType   |
      | title    | New item   |
      | closable | true       |
      | hidden   | true       |
    Then the response status must be 200
    And the form window "new-item" must be hidden and without form
    And the form window "new-item" must be live
    And the "FormWindow" must be re-rendered by the route "/items/new"
    When the form window is opened
    Then the response status must be 200
    And the form window "new-item" must contain the field "item[name]" with the value "" posted to "/items/new"

  Scenario: Load and open the form window of an existing item
    Given I am authenticated as "user"
    When the component renderer is asked for a "FormWindow" with the props:
      | windowId | item-2             |
      | formPath | /items/edit/item-2 |
      | formName | ItemType           |
      | objectId | item-2             |
      | title    | Item 2             |
      | hidden   | true               |
    Then the response status must be 200
    And the "FormWindow" must be re-rendered by the route "/items/edit/item-2"
    When the form window is opened
    Then the response status must be 200
    And the form window "item-2" must contain the field "item[name]" with the value "Item 2" posted to "/items/edit/item-2"

  Scenario: An administrator may load a form window on an administration path
    Given I am authenticated as "admin"
    When the component renderer is asked for a "FormWindow" with the form "ItemType" on the path "/admin/items/new"
    Then the response status must be 200
    And the "FormWindow" must be re-rendered by the route "/admin/items/new"

  Scenario: An administrator may load a form window on an administration path of a tenant
    Given I am authenticated as "admin"
    When the component renderer is asked for a "FormWindow" with the form "ItemType" on the path "/acme/admin/items/new"
    Then the response status must be 200
    And the "FormWindow" must be re-rendered by the route "/acme/admin/items/new"
    When the form window is opened
    Then the response status must be 200
    And the form window "dynamic-window" must contain the field "item[name]" with the value "" posted to "/acme/admin/items/new"

  Scenario: Load a form window on a path with the front controller of an application without URL rewriting
    Given I am authenticated as "user"
    When the component renderer is asked for a "FormWindow" with the form "ItemType" on the path "/index.php/items/new"
    Then the response status must be 200
    And the "FormWindow" must be re-rendered by the route "/index.php/items/new"
    When the form window is opened
    Then the response status must be 200
    And the form window "dynamic-window" must contain the field "item[name]" with the value "" posted to "/index.php/items/new"

  Scenario: Refuse a component without validator of its props
    Given I am authenticated as "user"
    When the component renderer is asked for a "Card" with the props:
      | title   | A card    |
      | content | Some text |
    Then the response status must be 403

  Scenario: Refuse a prop the validator of the FormWindow does not accept
    Given I am authenticated as "user"
    When the component renderer is asked for a "FormWindow" with the props:
      | windowId       | new-item   |
      | formPath       | /items/new |
      | formName       | ItemType   |
      | objectInstance | item-1     |
    Then the response status must be 400

  Scenario: Refuse a prop of the FormWindow with a wrong type
    Given I am authenticated as "user"
    When the component renderer is asked for a "FormWindow" with the props:
      | windowId | new-item   |
      | formPath | /items/new |
      | formName | ItemType   |
      | closable | yes        |
    Then the response status must be 400

  Scenario Outline: Refuse a malformed request
    Given I am authenticated as "user"
    When the component renderer receives the body '<body>'
    Then the response status must be 400

    Examples:
      | body                                  |
      | not a json body                       |
      | {"props": {"formPath": "/items/new"}} |
      | {"component": "FormWindow"}           |

  Scenario: The component renderer requires an authenticated user
    When the component renderer is asked for a "FormWindow" with the form "ItemType" on the path "/items/new"
    Then the response status must be 401
