Feature: List windows loaded dynamically through the component renderer
  As a user of an East application
  I want a list window rendered by the server to float over the current page
  So that several list windows are open together, each one listing its objects with the recipe of its route

  Background:
    Given a Symfony application with East UX
    And 7 items in the application

  Scenario: Load a floating list window, hidden until it is opened
    Given I am authenticated as "user"
    When the component renderer is asked for the list window "items" titled "Items" of the path "/items/"
    Then the response status must be 200
    And the list window "items" must be hidden and floating
    And the list window must show the items ""
    And the "ListWindow" must be re-rendered by the route "/items/"
    And the page of the list window must not be bound to the URL

  Scenario: Open a floating list window, its list loaded by the recipe of its path
    Given I am authenticated as "user"
    When the component renderer is asked for the list window "items" titled "Items" of the path "/items/"
    And the list window is opened
    Then the response status must be 200
    And the list window "items" must be shown and floating
    And the list window must show the items "Item 1, Item 2, Item 3, Item 4, Item 5"
    And the list window must be on the page 1 of 2
    And the list window "items" must be titled "Items"
    And the "ListWindow" must be re-rendered by the route "/items/"
    And the list window must provide a button opening the form window "items-new-object-form" of "/items/new"

  Scenario: Change the page of a floating list window, its window props kept
    Given I am authenticated as "user"
    When the component renderer is asked for the list window "items" titled "Items" of the path "/items/"
    And the list window is opened
    And the list window is re-rendered on the page 2
    Then the response status must be 200
    And the list window "items" must be shown and floating
    And the list window must show the items "Item 6, Item 7"
    And the list window must be on the page 2 of 2
    And the list window "items" must be titled "Items"
    And the page of the list window must not be bound to the URL

  Scenario: The access rules of the path of a list window apply when it is opened
    Given I am authenticated as "user"
    When the component renderer is asked for the list window "items" titled "Items" of the path "/admin/items/new"
    Then the response status must be 200
    When the list window is opened
    Then the response status must be 403

  Scenario: Refuse a list window which does not float
    Given I am authenticated as "user"
    When the component renderer is asked for the list window "items" titled "Items" of the path "/items/" without the prop "floating"
    Then the response status must be 400

  Scenario Outline: Refuse a prop the validator of the ListWindow does not accept
    Given I am authenticated as "user"
    When the component renderer is asked for the list window "items" titled "Items" of the path "/items/" with the prop "<prop>" set to "<value>"
    Then the response status must be 400

    Examples:
      | prop             | value              |
      | floating         | false              |
      | closeRedirectUrl | /home              |
      | newFormRoute     | fixtures_unknown   |
      | newFormRoute     | fixtures_item_edit |
      | itemComponent    | UnknownRow         |

  Scenario: The component renderer requires an authenticated user for a list window
    When the component renderer is asked for the list window "items" titled "Items" of the path "/items/"
    Then the response status must be 401
