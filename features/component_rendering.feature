Feature: Components of East UX in the pages rendered by East recipes
  As a developer of an East application
  I want to use the components of East UX in the templates of East Common's recipes
  So that a Live Component is re-rendered by the recipe of its page, with the objects loaded by this recipe

  Background:
    Given a Symfony application with East UX
    And 7 items in the application

  Scenario: Render a list window with the list recipe of East Common
    Given I am authenticated as "user"
    When I open the page "/items/"
    Then the response status must be 200
    And the list window must show the items "Item 1, Item 2, Item 3, Item 4, Item 5"
    And the list window must be on the page 1 of 2
    And the "ListWindow" must be re-rendered by the route "/items/"
    And the list window must provide the delete confirmation modal
    And the list window must provide a button opening the form window of "/items/new"

  Scenario: Re-render a list window through the recipe of its page
    Given I am authenticated as "user"
    When I open the page "/items/"
    And the list window is re-rendered on the page 2
    Then the response status must be 200
    And the list window must show the items "Item 6, Item 7"
    And the list window must be on the page 2 of 2
    And the "ListWindow" must be re-rendered by the route "/items/"

  Scenario: The Live Component endpoint requires an authenticated user
    Given I am authenticated as "user"
    When I open the page "/items/"
    And I am not authenticated
    And the list window is re-rendered on the page 2
    Then the response status must be 401

  Scenario: Render a form window with the creation recipe of East Common
    Given I am authenticated as "user"
    When I open the page "/items/new"
    Then the response status must be 200
    And the form window "item-new" must contain the field "item[name]" with the value "" posted to "/items/new"

  Scenario: Create an item and edit it in a form window
    Given I am authenticated as "user"
    When I submit the form of the page "/items/new" with the name "Created item"
    Then the client must be redirected to "/items/edit/item-8"
    When I open the page "/items/edit/item-8"
    Then the response status must be 200
    And the form window "item-item-8" must contain the field "item[name]" with the value "Created item" posted to "/items/edit/item-8"
