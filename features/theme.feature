Feature: Theme of the pages, chosen by the visitor
  As a visitor of an East application using the System.css x Bootstrap theme
  I want to choose the style (classic or modern) and the mode (day, night or automatic) of the pages
  So that the pages keep my choice, kept in my session

  Background:
    Given a Symfony application with East UX

  Scenario: Without choice, the pages are rendered with the default theme of the configuration
    When I open the page "/home"
    Then the response status must be 200
    And the page must be rendered with the style "legacy" and the mode "auto"
    And the page must have the classes "" and the Bootstrap theme ""
    And the choice of the theme must be sent to "/east-ux/theme"
    And the theme switcher must press the buttons "Automatic, Classic"

  Scenario: An anonymous visitor chooses the modern style and the night mode
    When I choose the theme '{"style": "modern", "mode": "dark"}'
    Then the response status must be 204
    When I open the page "/home"
    Then the response status must be 200
    And the page must be rendered with the style "modern" and the mode "dark"
    And the page must have the classes "system-modern" and the Bootstrap theme "dark"
    And the theme switcher must press the buttons "Night, Modern"

  Scenario: The visitor changes only the mode, the style is kept
    When I choose the theme '{"style": "modern"}'
    Then the response status must be 204
    When I choose the theme '{"mode": "light"}'
    Then the response status must be 204
    When I open the page "/home"
    Then the page must be rendered with the style "modern" and the mode "light"
    And the page must have the classes "system-modern" and the Bootstrap theme "light"
    And the theme switcher must press the buttons "Day, Modern"

  Scenario: An authenticated user keeps the theme on every page
    Given I am authenticated as "user"
    And 2 items in the application
    When I choose the theme '{"style": "legacy", "mode": "dark"}'
    Then the response status must be 204
    When I open the page "/items/"
    Then the response status must be 200
    And the page must be rendered with the style "legacy" and the mode "dark"
    And the page must have the classes "" and the Bootstrap theme "dark"
    And the list window must show the items "Item 1, Item 2"

  Scenario: Invalid choices are refused and change nothing
    When I choose the theme '{"style": "retro"}'
    Then the response status must be 400
    When I choose the theme '{"mode": "sepia"}'
    Then the response status must be 400
    When I choose the theme '{}'
    Then the response status must be 400
    When I send the theme '{"style": "modern"}' as "application/x-www-form-urlencoded"
    Then the response status must be 415
    When I open the page "/home"
    Then the page must be rendered with the style "legacy" and the mode "auto"
