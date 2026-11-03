Feature: Menu bar built from the menu generators of the application
  As a developer of an East application
  I want the menu bar to render the sections built by the menu generators tagged "teknoo.east.ux.menu.generator"
  So that every entry of the menu, the user dropdown included, comes from the application

  Background:
    Given a Symfony application with East UX

  Scenario: Without user, the menu bar has only its brand
    When I open the page "/home"
    Then the response status must be 200
    And the menu bar must link its brand to "/home"
    And the menu bar must show on the left the sections ""
    And the menu bar must show on the right the sections ""

  Scenario: The sections of a user, in the order of their positions
    Given I am authenticated as "user"
    When I open the page "/home"
    Then the response status must be 200
    And the menu bar must show on the left the sections "Home section, Content"
    And the menu bar must show on the right the sections "User <Tester>, Theme"
    And the section "Content" of the menu bar must contain the entries "All items (/items/), New item (/items/new)"
    And the section "User <Tester>" of the menu bar must contain the entries "My settings (/home?page=settings), ---, user@teknoo.test (/home), Logout (/home?page=logout)"
    And the menu bar must escape the label "User <Tester>"

  Scenario: The theme switcher added by the menu generator of East UX
    Given I am authenticated as "user"
    When I open the page "/home"
    Then the response status must be 200
    And the section "Theme" of the menu bar must contain the entries "Day (button), Night (button), Automatic (button), ---, Classic (button), Modern (button)"

  Scenario: The sections depend on the roles of the user
    Given I am authenticated as "admin"
    When I open the page "/home"
    Then the response status must be 200
    And the menu bar must show on the left the sections "Home section, Content, Administration"
    And the section "Administration" of the menu bar must contain the entries "New item (admin) (/admin/items/new)"
    And the menu bar must show on the right the sections "Admin <Tester>, Theme"

  Scenario: The menu bar is rendered in every page of the application
    Given I am authenticated as "user"
    And 2 items in the application
    When I open the page "/items/"
    Then the response status must be 200
    And the menu bar must show on the left the sections "Home section, Content"
    And the list window must show the items "Item 1, Item 2"

  Scenario: The entries opening a window carry the loader of the window and keep their link
    Given I am authenticated as "user"
    When I open the page "/home"
    Then the response status must be 200
    And the section "Content" of the menu bar must contain the entries "All items (/items/), New item (/items/new)"
    And the entry "All items" of the section "Content" must open a "ListWindow" window titled "All items"
    And the entry "New item" of the section "Content" must open a "FormWindow" window titled "New item"
    And the entry "Dashboard" of the section "Home section" must be a plain link

  Scenario: An entry of the menu opens its list window through the component renderer
    Given I am authenticated as "user"
    And 7 items in the application
    When I open the page "/home"
    And the component renderer is asked for the window of the entry "All items" of the section "Content"
    Then the response status must be 200
    And the list window "items" must be hidden and floating
    And the "ListWindow" must be re-rendered by the route "/items/"
    When the list window is opened
    Then the response status must be 200
    And the list window "items" must be shown and floating
    And the list window must show the items "Item 1, Item 2, Item 3, Item 4, Item 5"
    And the list window must be on the page 1 of 2
    And the list window "items" must be titled "All items"
    And the page of the list window must not be bound to the URL

  Scenario: An entry of the menu opens its form window through the component renderer
    Given I am authenticated as "user"
    When I open the page "/home"
    And the component renderer is asked for the window of the entry "New item" of the section "Content"
    Then the response status must be 200
    And the form window "menu-new-item" must be hidden and without form
    And the "FormWindow" must be re-rendered by the route "/items/new"
    When the form window is opened
    Then the response status must be 200
    And the form window "menu-new-item" must contain the field "item[name]" with the value "" posted to "/items/new"
