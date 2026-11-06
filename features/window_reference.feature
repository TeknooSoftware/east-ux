Feature: Windows running the methods sent through Mercure
  As a user of an East application
  I want a window to run the methods sent by a worker or by another window
  So that it shows the new data without polling the server

  Background:
    Given a Symfony application with East UX
    And 3 items in the application
    And I am authenticated as "user"

  Scenario: A window with a reference subscribes to its Mercure topic
    When I open the page "/items/followed/"
    Then the response status must be 200
    And the "ListWindow" must subscribe to its Mercure topic
    And the response must grant the topics of all the windows

  Scenario: A request can not choose the key of the reference in the view
    When I open the page "/items/followed/?viewKey=stolen"
    Then the response status must be 200
    And the "ListWindow" must subscribe to its Mercure topic

  Scenario: A window without reference does not subscribe
    When I open the page "/items/"
    Then the response status must be 200
    And the "ListWindow" must not subscribe to a Mercure topic

  Scenario: A window keeps its reference when the recipe of its page runs again
    When I open the page "/items/followed/"
    And I remember the reference of the "ListWindow"
    And the list window is re-rendered on the page 1
    Then the response status must be 200
    And the reference of the "ListWindow" must not have changed
    And the "ListWindow" must subscribe to its Mercure topic

  Scenario: The method refresh shows the new data of a list window
    When I open the page "/items/followed/"
    And I remember the reference of the "ListWindow"
    And the item "Item 4" is added to the application
    And the method "refresh" of the "ListWindow" is called
    Then the response status must be 200
    And the list window must show the items "Item 1, Item 2, Item 3, Item 4"
    And the reference of the "ListWindow" must not have changed

  Scenario: The method close hides a list window
    When I open the page "/items/followed/"
    And the method "close" of the "ListWindow" is called
    Then the response status must be 200
    And the "ListWindow" must be hidden

  Scenario: The method refresh keeps the form of a form window
    When I open the page "/items/followed/edit/item-2"
    And the method "refresh" of the "FormWindow" is called
    Then the response status must be 200
    And the form window "followed-item-item-2" must contain the field "item[name]" with the value "Item 2" posted to "/items/followed/edit/item-2"

  Scenario: The method close hides a form window
    When I open the page "/items/followed/edit/item-2"
    And the method "close" of the "FormWindow" is called
    Then the response status must be 200
    And the "FormWindow" must be hidden

  Scenario: An action which is not a Live action only renders the window again
    When I open the page "/items/"
    And the method "save" of the "ListWindow" is called
    Then the response status must be 200
    And the list window must show the items "Item 1, Item 2, Item 3"

  Scenario: Only a Live window can have a reference
    When I open the page "/items/followed/refused"
    Then the response status must be 400

  Scenario: Each window of a page has its own reference
    When I open the page "/items/followed/twice"
    Then the response status must be 200
    And the 2 windows of the page must have different Mercure topics

  Scenario: Another page asks a window to refresh
    When I open the page "/items/followed/"
    And the method "refresh" is published on the reference of the "ListWindow"
    Then the response status must be 200
    And the hub must have received the private method "refresh" for the "ListWindow"

  Scenario: A method out of the reference is refused
    When I open the page "/items/followed/"
    And the method "delete" is published on the reference of the "ListWindow"
    Then the response status must be 400
    And the hub must have received nothing
