# Menu

The `MenuBar` component renders sections built by `Teknoo\East\UX\Service\MenuService` from the **menu generators** of
the application. East UX has no route and no menu entry of its own: every entry, the user dropdown included (settings,
2FA, JWT, API keys, logout, ...), comes from a generator.

The menu classes are universal (`src/`, namespace `Teknoo\East\UX\`), only the component is Symfony-specific.

| Class or enum                                     | Role                                                                    |
|---------------------------------------------------|-------------------------------------------------------------------------|
| `Contracts\Menu\MenuGeneratorInterface`           | Implemented by the generators of the application                       |
| `Service\MenuService`                             | Runs the generators for a user and a location, sorts and caches the sections |
| `Contracts\Enum\Menu\Location`                    | `TopLeft` (`top-left`), `TopRight` (`top-right`)                        |
| `Contracts\Enum\Menu\Sorting`                     | `Append` (`append`), `Prepend` (`prepend`)                              |
| `Object\DTO\Menu\MenuSection`                     | A section: `name`, `position`, `icon`, `rawLabel`, `translationDomain`, its items |
| `Object\DTO\Menu\MenuItem`                        | An entry: `name`, `route`, `routeParameters`, `icon`, `divider`, `rawLabel`, `attributes`, `translationDomain`, `window` |
| `Object\DTO\Menu\MenuWindow`                      | The window opened in the page by an entry: `component`, `title`, `props` |
| `Service\Menu\ThemeMenuGenerator`                 | The generator of the theme switcher (see [theme.md](theme.md#in-the-menu-bar)) |

## Writing a generator

```php
<?php

declare(strict_types=1);

namespace App\Menu;

use Teknoo\East\Common\Contracts\User\UserInterface;
use Teknoo\East\UX\Contracts\Menu\MenuGeneratorInterface;
use Teknoo\East\UX\Contracts\Enum\Menu\Location;
use Teknoo\East\UX\Object\DTO\Menu\MenuItem;
use Teknoo\East\UX\Service\MenuService;

use function in_array;

class AdminMenu implements MenuGeneratorInterface
{
    public function generate(MenuService $menuService, Location $location, UserInterface $user, array $roles): void
    {
        if (Location::TopLeft !== $location || !in_array('ROLE_ADMIN', $roles, true)) {
            return;
        }

        $menuService->getSection('app.menu.administration', $location, 'icon-admin')
            ->addItem(new MenuItem(name: 'app.menu.users', route: 'app_admin_user_list', icon: 'icon-users'))
            ->addItem(new MenuItem(name: 'app.menu.items', route: 'app_admin_item_list'));
    }
}
```

`generate()` is called for each location the `MenuBar` renders, with the user and its roles (as a list). A generator
returns nothing: it gets sections from `MenuService::getSection()` and adds items to them. Several generators may add
items to the same section (same name and location).

A generator is a service tagged `teknoo.east.ux.menu.generator` (the interface is not autoconfigured):

```yaml
# config/services.yaml
services:
    App\Menu\AdminMenu:
        tags: ['teknoo.east.ux.menu.generator']
```

### Sections

```php
public function getSection(
    string $name,
    Location $location,
    string $icon = '',
    Sorting $position = Sorting::Append,
    bool $rawLabel = false,
    ?string $translationDomain = null,
): MenuSection
```

- The first call for a name and a location creates the section; the next calls return it, with the icon, the position
  and the flag of the first call.
- Position: an appended section is placed after the sections already created **for its location**, whatever the order
  of the generators; a prepended section gets the position 0 and goes before the appended ones (prepended sections keep
  their creation order). The sections are sorted by position when the menu is built.
- `MenuSection::addItem(MenuItem $item, Sorting $position = Sorting::Append)` appends the item, or prepends it in the
  section.

### Items, dividers and raw labels

```php
new MenuItem(
    name: 'app.menu.settings',      // translation key, or the label itself when rawLabel is true
    route: 'app_settings',          // route name, rendered with path(route, routeParameters)
    routeParameters: [],
    icon: 'icon-settings',          // CSS class(es) of the icon, rendered in a span.menu-icon
    divider: false,                 // a separator between two entries: name and route are not rendered
    rawLabel: false,                // name displayed as is instead of being translated
    attributes: [],                 // HTML attributes of the link or the button, escaped
    translationDomain: null,        // domain of the name, the default domain of the application when null
    window: null,                   // MenuWindow: the window opened in the page instead of following the link
);
```

- Labels (`name` of the sections and of the items) are translation keys of the application, translated in its
  **default domain**, or in the `translationDomain` of the section or the item (the entries of
  `ThemeMenuGenerator` use `east_ux`).
- An item without route (and not a divider) is rendered as `<button type="button" class="dropdown-item">`: an entry
  handled by a Stimulus controller, bound with its `attributes`:

  ```php
  new MenuItem(
      name: 'app.menu.night',
      route: '',
      attributes: ['data-action' => 'click->theme#select', 'data-theme-mode-param' => 'dark'],
  );
  ```

  `attributes` are added to the link or the button, names and values escaped.
- `rawLabel: true` displays the name as is: for a label that is not a translation key, such as a user's display name.
  The label is still escaped by Twig: a raw label is never rendered with `|raw`.
- A divider is written `new MenuItem(name: '', route: '', divider: true)` and rendered as
  `<li><hr class="dropdown-divider"></li>`.
- Icons: `icon` holds the CSS classes of an icon of the application's icon set, rendered as
  `<span class="menu-icon <icon>" aria-hidden="true"></span>`; East UX's stylesheet only sizes and aligns `.menu-icon`.

### Entries opening a window

An item with a route and a `window` (`Teknoo\East\UX\Object\DTO\Menu\MenuWindow`) opens its window in the current
page, rendered by the component renderer ([live-components.md](live-components.md#dynamic-windows)), instead of
leaving the page:

```php
new MenuItem(
    name: 'app.menu.items',
    route: 'app_item_list',
    window: new MenuWindow(
        component: 'ListWindow',            // a component accepted by the component renderer
        title: 'app.item.title.list',       // translation key, translated in the domain of the item
        props: [                            // props of the component, a JSON object (none by default)
            'windowId' => 'app-items',
            'floating' => true,
            'closable' => true,
            'draggable' => true,
            'itemComponent' => 'ItemRow',
            'tableHeaders' => ['app.item.table.name', 'app.item.table.actions'],
            'newFormRoute' => 'app_item_new',
            'newFormName' => 'App\\Form\\Type\\ItemType',
        ],
    ),
);
```

- The link keeps its `href`: it is the fallback, followed without JavaScript, on a click with the middle button or a
  modifier key, and when the window can not be loaded. `MenuBar` adds the `window-dynamic-loader` controller to the
  link, merged with the `data-controller` and the `data-action` of its `attributes`, and its values `url`
  (`path('_teknoo_east_ux_component_render')`), `component`, `title` (translated) and `props` (JSON, `{}` when
  empty). The loader adds `originalPath` (the path and the query of the `href`), `hidden: true` and the title to the
  props ([stimulus.md](stimulus.md#window-dynamic-loader)).
- The application must import the routes of the bundle (`@TeknooEastUXBundle/config/routes.yaml`, see
  [live-components.md](live-components.md#application-setup)): without them,
  `path('_teknoo_east_ux_component_render')` throws while the menu bar is rendered, and the page is not rendered (an
  error).
- `window-dynamic-loader` must be enabled in the `assets/controllers.json` of the application
  ([stimulus.md](stimulus.md#with-assetmapper-and-stimulusbundle)).
- The layout of the application must hold `<div id="dynamic-modals"></div>` once per page
  ([stimulus.md](stimulus.md#contract-with-the-page)): without it, the entries follow their link.
- The props are checked by the validator of the component: a prop it refuses gives a 400, and the link is followed.
- A `ListWindow` needs `floating: true` (required by `ListWindowPropsValidator`) and should be `closable` and
  `draggable`: `window-drag` brings a floating window to the front ([components.md](components.md#listwindow)).
- A `FormWindow` needs `formPath` (required by `FormWindowPropsValidator`), and the `href` of its item should equal
  `formPath`: the window is re-rendered by the recipe of `formPath`, which is also the page opened when this render
  fails. A generator of the infrastructure gets it from Symfony's `UrlGeneratorInterface`.
- `title` is a translation key, translated in the `translationDomain` of the item (the default domain when null), and
  so are the props `closeAriaLabel` and `resizeAriaLabel` when given. The other props are sent as they are.
- `windowId` names the window in the page: a second click opens the window already loaded instead of loading it again.
- `MenuWindow` is universal (`src/`): a generator of the domain can build it, with its form types written as strings.
- The window of an item without route is ignored: the item is a button, without loader.

### The user dropdown

The user dropdown is a `Location::TopRight` section titled with the display name of the user, filled by a generator of
the application. A generator that needs more than the East user (for example the security user, to find the 2FA
route) injects the services it needs, such as Symfony's `TokenStorageInterface`.

```php
class UserMenu implements MenuGeneratorInterface
{
    public function generate(MenuService $menuService, Location $location, UserInterface $user, array $roles): void
    {
        if (Location::TopRight !== $location) {
            return;
        }

        $menuService->getSection((string) $user, $location, 'icon-user', rawLabel: true)
            ->addItem(new MenuItem(name: 'app.menu.settings', route: 'app_my_settings'))
            ->addItem(new MenuItem(name: 'app.menu.api_keys', route: 'app_my_api_keys'))
            ->addItem(new MenuItem(name: '', route: '', divider: true))
            ->addItem(new MenuItem(name: 'app.menu.logout', route: 'app_logout'));
    }
}
```

## The menu service

- `getMenuFor(UserInterface $user, Location $location): array<int, MenuSection>` runs every generator for the user and
  the location, on a fresh copy of the service, sorts the sections by position and caches them.
- The cache is kept **in the instance**, per user identifier and location, until `reset()`. The service implements
  Symfony's `ResetInterface` and is tagged `kernel.reset`: a menu is never kept from a request to the next one (in a
  worker such as FrankenPHP) or from a test to the next one.
- The service is defined by the bundle (`Teknoo\East\UX\Service\MenuService`, its argument is the tagged iterator of the
  generators); it can be injected in the application's services.

## Rendering

`<twig:MenuBar :user="..." />` (see [components.md](components.md#menubar)) renders the `Location::TopLeft` sections
on the left and the `Location::TopRight` sections on the right, each as a Bootstrap dropdown, with the brand linked to
`teknoo_east_ux.home_route`. Without user, no section is rendered.

Before a link of the menu bar leaves a page where floating windows are shown (a floating `ListWindow`, a `FormWindow`
overlay), the `leave-confirm` controller of the `<nav>` asks a confirmation in a `ConfirmModal` that `MenuBar` renders
after the `<nav>`, with the texts `teknoo.east.ux.menu_bar.*` ([theming.md](theming.md#translations)). It concerns the
links that do not open a window: the brand, the entries without window (settings pages, logout, ...) and an entry whose
window is the window of the page, not live, so its link is followed ([stimulus.md](stimulus.md#window-dynamic-loader));
not the clicks that open a window, the buttons or the clicks with a modifier key
([stimulus.md](stimulus.md#leave-confirm)). `leave-confirm` must be enabled in the `assets/controllers.json` of the
application; without it, the links are followed as before.
