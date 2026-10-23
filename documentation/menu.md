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
| `Object\DTO\Menu\MenuItem`                        | An entry: `name`, `route`, `routeParameters`, `icon`, `divider`, `rawLabel`, `attributes`, `translationDomain` |
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
