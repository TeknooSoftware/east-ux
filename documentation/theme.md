# System.css x Bootstrap theme

East UX ships the [System.css x Bootstrap](https://github.com/TeknooSoftware/system.css-x-bootstrap) theme, a retro
Apple System OS theme for Bootstrap 5.3, and everything a page needs to let its visitors choose how it looks:

- **the style**: `legacy`, the classic System OS rendering, or `modern`, the colourful "System Modern" rendering;
- **the mode**: `light`, `dark` (the Night palette), or `auto`, which follows the `prefers-color-scheme` setting of the
  browser.

The pieces:

- the stylesheet and fonts of the theme, served by AssetMapper;
- Twig functions that write the class and attributes of `<html>`, plus a script that applies the theme before the first
  paint;
- the `theme` Stimulus controller, which switches the theme without reloading the page and notifies the server;
- a `ThemeSwitcher` component and a menu generator, `ThemeMenuGenerator`;
- the storage of the choice, either in the session (an endpoint of the bundle, replaceable by a storage of the
  application, for example in the account of the user) or in the browser (localStorage).

## Assets

| File (in `assets/dist/system-theme/`)        | Logical path with AssetMapper                                        |
|----------------------------------------------|----------------------------------------------------------------------|
| `css/bootstrap-system-theme.min.css` (and `.css`) | `@teknoo/east-ux/system-theme/css/bootstrap-system-theme.min.css` |
| `fonts/{ChicagoFLF,monaco,ChiKareGo2}.{woff,woff2}` | `@teknoo/east-ux/system-theme/fonts/...`                      |
| `LICENSE`                                    | The MIT license of the theme                                         |

The theme is an overlay of Bootstrap 5.3 (built for 5.3.8). Bootstrap itself is not shipped: the application provides
it (an entry of its importmap), as for the components. Load the stylesheets in this order: Bootstrap, `east-ux.css`,
the theme, then the stylesheet of the application.

### With AssetMapper

Symfony Flex writes the importmap entry `@teknoo/east-ux/system-theme/css/bootstrap-system-theme.min.css` into
`importmap.php` (only if that file already exists, see [stimulus.md](stimulus.md#loading-the-controllers)). The
application imports it from its entry point:

```js
// assets/app.js
import './stimulus_bootstrap.js';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@teknoo/east-ux/styles/east-ux.css';
import '@teknoo/east-ux/system-theme/css/bootstrap-system-theme.min.css';
import './styles/app.css';
```

The stylesheet loads its fonts with relative URLs (`url(../fonts/...)`), which AssetMapper rewrites to the versioned
files. The layout may preload the legacy font, ready when the visitor switches back from the modern style:

```twig
{{ importmap('app') }}
<link rel="preload" href="{{ asset('@teknoo/east-ux/system-theme/fonts/ChiKareGo2.woff2') }}" as="font" type="font/woff2" crossorigin>
```

An application that served an older copy of the theme replaces it with this one. Before the version shipped here,
`data-bs-theme="dark"` was neutralised by the theme and the pages stayed white.

### Updating the theme

`make system-theme` copies the stylesheets, the fonts and the license from a clone of the theme repository
(`SYSTEM_THEME_DIR`, `../../ui/system.css-x-bootstrap` by default):

```sh
make system-theme SYSTEM_THEME_DIR=/path/to/system.css-x-bootstrap
```

## Configuration

```yaml
# config/packages/teknoo_east_ux.yaml
teknoo_east_ux:
    theme:
        style: 'legacy'      # legacy | modern
        mode: 'auto'         # light | dark | auto
        storage: 'session'   # session | browser
        update_route: '_teknoo_east_ux_theme_update'
```

| Key                  | Default                          | Meaning                                                                 |
|----------------------|----------------------------------|-------------------------------------------------------------------------|
| `theme.style`        | `legacy`                         | Style rendered while the visitor has not chosen                         |
| `theme.mode`         | `auto`                           | Mode rendered while the visitor has not chosen                          |
| `theme.storage`      | `session`                        | `session`: the choice is sent to `update_route` and kept on the server; `browser`: the choice is kept in the localStorage |
| `theme.update_route` | `_teknoo_east_ux_theme_update`   | Route receiving the choice with the `session` storage                   |

With the `session` storage, import the route of the theme with a **public** prefix: visitors choose a theme on the
login page too.

```yaml
# config/routes/east_ux.yaml
teknoo_east_ux_theme:
    resource: '@TeknooEastUXBundle/config/theme_routes.yaml'
    prefix: '/east-ux'
```

## The page

The class and the attributes are set on `<html>`, and the script goes in `<head>`, before the stylesheets:

```twig
<!DOCTYPE html>
<html lang="en" class="{{ east_ux_theme_class() }}" {{ east_ux_theme_attributes() }}>
    <head>
        <meta charset="UTF-8">
        {{ east_ux_theme_script() }}
        {# stylesheets, importmap() ... #}
    </head>
```

A page in the modern style and the dark mode is rendered as:

```html
<html lang="en" class="system-modern" data-bs-theme="dark" data-controller="theme" data-theme-style-value="modern"
      data-theme-mode-value="dark" data-theme-storage-value="session" data-theme-url-value="/east-ux/theme">
```

| Function                                        | Renders                                                                  |
|-------------------------------------------------|--------------------------------------------------------------------------|
| `east_ux_theme()`                               | The `ThemePreference` of the visitor (`.style.value`, `.mode.value`), for the application's own conditions |
| `east_ux_theme_class(style, mode)`              | `system-modern` for the modern style, an empty string otherwise          |
| `east_ux_theme_attributes(style, mode, url)`    | `data-bs-theme` and the `theme` controller with its values (style, mode, storage, URL notified of the changes), escaped |
| `east_ux_theme_script(nonce)`                   | An inline `<script>` that resolves the `auto` mode and, with the `browser` storage, applies the choice kept in the localStorage, before the first paint |

- All the arguments are optional. Without them, the functions render the preference of the visitor (the stored one,
  or the default one of the configuration). An application that keeps the choice somewhere else, such as in its
  database, passes its own values, for example `east_ux_theme_attributes(app.user.themeStyle, app.user.themeMode)`.
  An invalid value is replaced by the preference of the visitor.
- `url` sets the URL notified of the changes. With the `session` storage it defaults to the route `update_route` (none
  when the route is not imported). With the `browser` storage there is none unless the argument is given.
- `nonce` is the CSP nonce of the inline script, for a page served with a `Content-Security-Policy` that forbids inline
  scripts.
- `east_ux_theme_attributes()` writes the `data-controller` attribute of `<html>`. Another controller cannot be added to
  `<html>` with a second attribute; put it on `<body>` instead.
- `east_ux_theme_script()` reads the values written by `east_ux_theme_attributes()` on `<html>`: use them together.

How a preference is rendered on `<html>` (`ThemeService`, the script and the controller apply the same rules):

| Style  | Mode  | Class           | `data-bs-theme`                                                         |
|--------|-------|-----------------|-------------------------------------------------------------------------|
| legacy | light | none            | none                                                                    |
| modern | light | `system-modern` | `light`                                                                 |
| any    | dark  | by the style    | `dark`                                                                  |
| any    | auto  | by the style    | rendered as `light` by the server; the script and the controller set `dark` when the browser prefers a dark color scheme, and follow its changes |

## Switching the theme

The `theme` controller is set on `<html>`, so a switch can be anywhere in the page. A switch is a button bound to its
`select` action, with the value it selects as a Stimulus param:

```html
<button type="button" data-action="click->theme#select" data-theme-mode-param="dark" data-theme-target="option">Night</button>
<button type="button" data-action="click->theme#select" data-theme-style-param="modern" data-theme-target="option">Modern</button>
<button type="button" data-action="click->theme#toggleMode">Day / Night</button>
```

The `option` targets get the class `active` and `aria-pressed="true"` when their value is the current one. The
`menuOption` targets, for the entries of a menu (`role="menuitemradio"`), get `aria-checked="true"` instead, which
`east-ux.css` draws as a check mark on the left of the entry, as in the menus of System OS.

### The ThemeSwitcher component

```twig
<twig:ThemeSwitcher class="ms-lg-3" />
<twig:ThemeSwitcher :showStyle="false" />
```

It renders a group of buttons for the modes (`showMode`, `true`) and a group for the styles (`showStyle`, `true`),
with the buttons of the current preference pressed. Unlike the menu bar, it is rendered for anonymous visitors too,
for example on the login page. To keep it where the "Theme" section of the connected users is, render it in the
`right_sections` block of the `MenuBar` when there is no user:

```twig
<twig:MenuBar :user="user">
    <twig:block name="right_sections">
        {% if user %}
            {{ parent() }}
        {% else %}
            <twig:ThemeSwitcher class="ms-auto" />
        {% endif %}
    </twig:block>
</twig:MenuBar>
```

### In the menu bar

`Teknoo\East\UX\Service\Menu\ThemeMenuGenerator` adds the modes (Day, Night, Automatic), a divider and the styles
(Classic, Modern) to the menu bar, as buttons of the `theme` controller (see [menu.md](menu.md#items-dividers-and-raw-labels)),
the current mode and style checked.
The bundle defines the service without tagging it. To get a "Theme" section on the right of the menu bar, tag it. The
sections are placed in the order the generators run (by decreasing priority): give it a priority lower than the one of
the user dropdown's generator to place it after the dropdown.

```yaml
# config/services.yaml
services:
    Teknoo\East\UX\Service\Menu\ThemeMenuGenerator:
        tags:
            - { name: 'teknoo.east.ux.menu.generator', priority: -10 }
```

Its arguments are `location` (`Location::TopRight`), `withMode` (`true`), `withStyle` (`true`) and `icon` (`''`), for
example `location: !php/enum Teknoo\East\UX\Contracts\Enum\Menu\Location::TopLeft`.

To add the entries to a section of the application instead, such as the user dropdown, inject the service in the
application's generator and call `addItemsTo()`:

```php
public function generate(MenuService $menuService, Location $location, UserInterface $user, array $roles): void
{
    if (Location::TopRight !== $location) {
        return;
    }

    $section = $menuService->getSection((string) $user, $location, 'icon-user', rawLabel: true);
    $section->addItem(new MenuItem(name: 'app.menu.settings', route: 'app_my_settings'));
    $section->addItem(new MenuItem(name: '', route: '', divider: true));
    $this->themeMenuGenerator->addItemsTo($section);
}
```

The labels are keys of the `east_ux` domain (`teknoo.east.ux.theme_switcher.*`, see
[theming.md](theming.md#translations)).

## Storage of the choice

### In the session (`storage: session`)

On each change, the controller sends `POST <url>` with the JSON body `{"style": "modern", "mode": "dark"}` and the
headers `Content-Type: application/json` and `X-Requested-With: XMLHttpRequest`. The body may also carry only one of
the two values. The controller `ThemePreferenceUpdater` saves the choice with `ThemeService`:

- `204` when the choice is saved;
- `400` when the body has neither style nor mode, or has an invalid value;
- `415` when the body is not JSON. A cross-site form cannot send a JSON body, and a cross-site script needs the
  consent of the application (CORS preflight), so the endpoint needs no CSRF token.

`Teknoo\East\UXBundle\Theme\SessionThemePreferenceStorage` keeps the choice in the session, under
`teknoo_east_ux_theme`. It reads the session only when the request comes with an existing session, so rendering a
page never starts one.

### In the storage of the application

`ThemeService` reads and writes the choice through the service
`Teknoo\East\UX\Contracts\Theme\ThemePreferenceStorageInterface`. With the `session` storage, the bundle aliases it to
the session storage. An alias defined by the application wins, for example a storage in the account of the user:

```php
<?php

declare(strict_types=1);

namespace App\Theme;

use DomainException;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Contracts\Theme\ThemePreferenceStorageInterface;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\Recipe\Promise\PromiseInterface;

class UserThemePreferenceStorage implements ThemePreferenceStorageInterface
{
    public function __construct(
        private readonly CurrentUser $currentUser,      // the application's access to the authenticated user
        private readonly UserWriter $writer,
    ) {
    }

    public function load(PromiseInterface $promise): ThemePreferenceStorageInterface
    {
        $user = $this->currentUser->get();
        $style = Style::tryFrom((string) $user?->getThemeStyle());
        $mode = Mode::tryFrom((string) $user?->getThemeMode());

        if (null === $style || null === $mode) {
            // The default preference of the configuration is used
            $promise->fail(new DomainException('No theme chosen'));

            return $this;
        }

        $promise->success(new ThemePreference($style, $mode));

        return $this;
    }

    public function save(ThemePreference $preference): ThemePreferenceStorageInterface
    {
        $user = $this->currentUser->get();
        if (null !== $user) {
            $user->setThemeStyle($preference->style->value)->setThemeMode($preference->mode->value);
            $this->writer->save($user);
        }

        return $this;
    }
}
```

```yaml
# config/services.yaml
services:
    Teknoo\East\UX\Contracts\Theme\ThemePreferenceStorageInterface:
        alias: 'App\Theme\UserThemePreferenceStorage'
```

### In the browser (`storage: browser`)

The choice is kept in the localStorage of the browser, under `east-ux-theme:style` and `east-ux-theme:mode`. The
script of `east_ux_theme_script()` applies it before the first paint, and the open tabs of the application follow a
change made in one of them. Nothing is sent to the server, unless:

- the `url` argument of `east_ux_theme_attributes()` names an endpoint of the application, which then receives the
  same JSON body as the session endpoint;
- or the application listens to the `theme:changed` event, dispatched on `<html>` after each change with
  `{style, mode}` in its `detail`:

```js
document.documentElement.addEventListener('theme:changed', (event) => {
    console.log(event.detail.style, event.detail.mode);
});
```

To render a choice saved in its database, the application passes it to the Twig functions, or aliases
`ThemePreferenceStorageInterface` to its own storage (the server then renders it, while the browser still keeps the
latest choice of the device).

## The `theme` controller

| Values (default)                                               | Actions                              | Targets  | Event           |
|----------------------------------------------------------------|--------------------------------------|----------|-----------------|
| `style` (`legacy`), `mode` (`auto`), `storage` (`session`), `url`, `storage-key` (`east-ux-theme`), `modern-class` (`system-modern`), `switching-class` (`east-ux-theme-switching`) | `select` (params `style`, `mode`), `toggleStyle`, `toggleMode` | `option`, `menuOption` | `theme:changed` |

- `select` changes the style and/or the mode given as params. `toggleStyle` switches between legacy and modern.
  `toggleMode` switches between light and dark; from the auto mode, it switches to the opposite of the current
  appearance.
- While it switches, the controller adds the class `east-ux-theme-switching` to `<html>`. A rule of `east-ux.css`
  disables the transitions under this class, so the colours do not fade.
- In the auto mode, it follows the changes of `prefers-color-scheme`.
- A failed request to `url` is reported with `console.error()`; the page keeps the new theme.
