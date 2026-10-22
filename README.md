Teknoo Software - East UX
=========================

[![Latest Stable Version](https://poser.pugx.org/teknoo/east-ux/v/stable)](https://packagist.org/packages/teknoo/east-ux)
[![Latest Unstable Version](https://poser.pugx.org/teknoo/east-ux/v/unstable)](https://packagist.org/packages/teknoo/east-ux)
[![Total Downloads](https://poser.pugx.org/teknoo/east-ux/downloads)](https://packagist.org/packages/teknoo/east-ux)
[![License](https://poser.pugx.org/teknoo/east-ux/license)](https://packagist.org/packages/teknoo/east-ux)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)

Symfony bundle, following the #East programming philosophy, built on Teknoo/East-Foundation and Teknoo/East-Common,
providing the application-level UI pieces of an East application:

* Symfony UX Twig and Live Components, with their templates: windows (`Window`, `FormWindow`, `ListWindow`,
  `LoginWindow`), `ObjectList`, `Pagination`, `ConfirmModal`, `Card` and `MenuBar`;
* their Stimulus controllers and a structural stylesheet, served by AssetMapper without build step (or consumed with
  npm);
* a component renderer endpoint, to load a `FormWindow` dynamically;
* a menu service, fed by the menu generators of the application.

The Live Components are re-rendered by the East recipe of their page: East Foundation's router maps a
`POST /_components/<Name>` back to the route of the component's `originalPath` and runs its recipe again, and East
Common's `LiveComponentBuilder` copies the view parameters of the recipe into the component. East UX only uses this
bridge: the router and `LiveTrait` stay in East Foundation, the `LiveComponentBuilder` and the recipe steps in East
Common.

Documentation
-------------

* [Components](documentation/components.md): props, blocks and behaviour of each component.
* [Live Components and the East bridge](documentation/live-components.md): re-renders through the recipes, the
  component renderer, security, limitations.
* [Stimulus controllers](documentation/stimulus.md): values of each controller and contract with the page.
* [Menu](documentation/menu.md): menu generators, sections, items and the menu service.
* [Theming](documentation/theming.md): stylesheet, form theme, template overrides and translations.

Installation & Requirements
---------------------------

This library requires:

    * PHP 8.4+
    * A PHP autoloader (Composer is recommended)
    * Teknoo/East-Foundation 9.4+
    * Teknoo/East-Common 4.8+
    * Symfony 7.4+ or 8.1+ (FrameworkBundle, Form, Security HTTP, Validator), with SecurityBundle registered and
      `framework.validation` enabled in the application
    * Symfony UX TwigComponent 3 and LiveComponent 3
    * Twig 3.24+
    * Optional: Symfony AssetMapper and StimulusBundle, to serve the controllers and the stylesheet without build step

```sh
composer require teknoo/east-ux
```

Symfony configuration
---------------------

### Bundles

```php
// config/bundles.php
return [
    // ...
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Teknoo\DI\SymfonyBridge\DIBridgeBundle::class => ['all' => true],
    Teknoo\East\FoundationBundle\EastFoundationBundle::class => ['all' => true],
    Teknoo\East\CommonBundle\TeknooEastCommonBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Symfony\UX\LiveComponent\LiveComponentBundle::class => ['all' => true],
    Teknoo\East\UXBundle\TeknooEastUXBundle::class => ['all' => true],
];
```

East Foundation and East Common are configured as their own documentation explains (`di_bridge.definitions`, ...).
East UX has no PHP-DI definition today: its `infrastructures/symfony/config/di.php` and `twig.php` are empty, listing
them in `di_bridge.definitions` is optional.

The bundle prepends:

* `twig_component.defaults` with `Teknoo\East\UXBundle\Components\` mapped to `@TeknooEastUX/components/`: the
  components keep their short names (`<twig:FormWindow>`, `component('ListWindow', {...})`). The application still
  configures `twig_component` (`anonymous_template_directory` and its own `defaults`, both required by
  TwigComponentBundle);
* when AssetMapper is available, `framework.asset_mapper.paths` with the package's `assets/dist` directory, as the
  namespace `@teknoo/east-ux`.

### Bundle configuration

```yaml
# config/packages/teknoo_east_ux.yaml
teknoo_east_ux:
    home_route: 'app_home'                       # brand link of the MenuBar
    form_theme: '@App/form/fields.html.twig'     # form theme of the FormWindow
    component_rendering:
        allow_unreferenced_components: false     # render a component without validator of its props
```

| Key                                                   | Default | Meaning                                                                         |
|-------------------------------------------------------|---------|---------------------------------------------------------------------------------|
| `home_route`                                          | `null`  | Route of the brand link of `MenuBar`; without it, the brand links to `/`        |
| `form_theme`                                          | `null`  | Default of the `formTheme` prop of `FormWindow`; without it, no theme is set on the form and the global form themes apply |
| `component_rendering.allow_unreferenced_components`   | `false` | Lets the component renderer render a component without validator of its props   |

A configured value can not be an empty string. The values are available as the parameters
`teknoo_east_ux.home_route`, `teknoo_east_ux.form_theme` and
`teknoo_east_ux.component_rendering.allow_unreferenced_components`. The validators of the props use Symfony's
validator: the application enables `framework.validation` (the default `config/packages/validator.yaml` of the
Symfony recipe does).

### Routes and access control

```yaml
# config/routes/east_ux.yaml: the component renderer answers on `POST /component/render`
east_ux:
    resource: '@TeknooEastUXBundle/config/routes.yaml'
    prefix: '/component'

# config/routes/ux_live_component.yaml: the Live Component endpoint, re-routed by East Foundation
live_component:
    resource: '@LiveComponentBundle/config/routes.php'
    prefix: '/_components'
```

```yaml
# config/packages/security.yaml
security:
    access_control:
        # ...
        - { path: '^/component', roles: [IS_AUTHENTICATED_FULLY], requires_channel: https }
        - { path: '^/_components', roles: [IS_AUTHENTICATED_FULLY], requires_channel: https }
```

The component renderer renders the component and the props of its JSON body: the access rules of the application
(here `^/component`) decide who may call it. East Foundation re-runs, for a `/_components` request, the recipe of the
route named by the signed `originalPath`, after checking the access rules of that route with the firewall (East
Foundation 9.4). The details are in [documentation/live-components.md](documentation/live-components.md).

Components
----------

| Component      | Kind | Purpose                                                                            |
|----------------|------|------------------------------------------------------------------------------------|
| `Window`       | Twig | Generic window (title bar, separator, pane), base of the other windows' templates  |
| `FormWindow`   | Live | Window holding a Symfony form, as a modal loaded dynamically or as a full page      |
| `ListWindow`   | Live | Paginated list of objects with an optional search form and a "New" button         |
| `LoginWindow`  | Twig | Login form of Symfony's `form_login`, error in `#login-error`                       |
| `MenuBar`      | Twig | Navigation bar built from the menu generators                                     |
| `ObjectList`   | Twig | Table of objects, one item component per row                                      |
| `Pagination`   | Twig | Page buttons bound to the `page` prop of `ListWindow`                             |
| `ConfirmModal` | Twig | Bootstrap confirmation modal, used by `ListWindow` for deletions                  |
| `Card`         | Twig | Bootstrap card; its content is escaped unless `contentIsHtml` is set              |

Every template can be overridden in `templates/bundles/TeknooEastUXBundle/components/<Name>.html.twig`. Texts are
translation keys of the `east_ux` domain, with an English catalogue; labels supplied by the application (menu entries,
list headers, sub-form labels) keep its default domain. See [documentation/components.md](documentation/components.md)
and [documentation/theming.md](documentation/theming.md).

The East bridge
---------------

A page is rendered by an East recipe, and the Live Components it holds receive the view parameters of this recipe:

```yaml
# config/routes/item.yaml
app_item_list:
    path: '/items/'
    methods: ['GET', 'POST']
    defaults:
        _controller: 'teknoo.east.common.endpoint.crud.list'
        loader: '@App\Loader\ItemLoader'
        defaultOrderDirection: 'ASC'
        itemsPerPage: 20
        template: '@@App/item/list.html.twig'
        errorTemplate: '@@App/Error/<error>.html.twig'
```

```twig
{# templates/item/list.html.twig #}
<twig:ListWindow
    title="{{ 'app.item.title.list'|trans }}"
    objectsCollection="{{ objectsCollection }}"
    pageCount="{{ pageCount }}"
    page="{{ page }}"
    itemComponent="ItemRow"
    tableHeaders="{{ ['app.item.table.name', 'app.item.table.actions'] }}"
    newFormRoute="app_item_new"
    newFormName="App\\Form\\Type\\ItemType"
/>
<div id="dynamic-modals"></div>
```

When a page button is clicked, the `ListWindow` is re-rendered: `POST /_components/ListWindow` runs the recipe of
`/items/` again for the new page, and the component receives the new `objectsCollection` and `pageCount`. The "New"
button asks the component renderer for a `FormWindow` on `/items/new`, appends it to `#dynamic-modals` and opens it:
the window is re-rendered by the creation recipe, which builds the form. Components never load objects themselves.
See [documentation/live-components.md](documentation/live-components.md).

Menu
----

```php
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
        if (Location::TopLeft === $location && in_array('ROLE_ADMIN', $roles, true)) {
            $menuService->getSection('app.menu.administration', $location)
                ->addItem(new MenuItem(name: 'app.menu.users', route: 'app_admin_user_list'));
        }
    }
}
```

```yaml
# config/services.yaml
services:
    App\Menu\AdminMenu:
        tags: ['teknoo.east.ux.menu.generator']
```

```twig
<twig:MenuBar :user="app.user ? app.user.wrappedUser : null" />
```

The menu service caches the sections per user and location in its instance, and is reset between two requests
(`kernel.reset`). See [documentation/menu.md](documentation/menu.md).

Assets
------

### AssetMapper and StimulusBundle

`assets/package.json` is read by Symfony Flex (the package has the `symfony-ux` keyword) and by StimulusBundle. When
the library is installed or updated, Flex writes the `@teknoo/east-ux` entries into the application's
`assets/controllers.json` (the seven controllers) and `importmap.php` (`@teknoo/east-ux/styles/east-ux.css`, and
`@hotwired/stimulus` and `bootstrap` when they are missing), each file only if it already exists: commit both files
first, then require the library and commit the entries Flex adds. A later `composer install` leaves them unchanged.

The application imports the stylesheet in its `assets/app.js`:

```js
import './stimulus_bootstrap.js';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@teknoo/east-ux/styles/east-ux.css';
import './styles/app.css';
```

`bin/console debug:asset-map` lists the assets of the library under `@teknoo/east-ux/` (for example
`@teknoo/east-ux/controllers/form_window_controller.js` and `@teknoo/east-ux/styles/east-ux.css`).

### npm

The same `assets/package.json` is a valid npm package. In an application still on npm (a `package.json` and no
`importmap.php`), Flex adds `"@teknoo/east-ux": "file:vendor/teknoo/east-ux/assets"` to `package.json` when the
library is installed or updated (in `devDependencies`, or where the key already is: move it to `dependencies`, the
browser loads the package at runtime), and writes the seven controllers into `assets/controllers.json` if that file
exists. Run `npm install` after Composer. The application imports the controllers from
`@teknoo/east-ux/dist/controllers/`, registers them with their identifiers (`form-window`,
`form-window-dynamic-loader`, `list-window-refresh`, `window-close`, `window-drag`, `delete-item`, `clipboard`) and
loads `@teknoo/east-ux/dist/styles/east-ux.css`. Without a bundler, its import map needs the prefix entry
`"@teknoo/east-ux/": "<public URL of node_modules>/@teknoo/east-ux/"`.

See [documentation/stimulus.md](documentation/stimulus.md) for the controllers and the contract with the page
(`#dynamic-modals`, `delete-confirm-modal`, the `list-window:refresh` event, the `X-Requested-With` header and the CSRF
token).

Support this project
---------------------
This project is free and will remain free. It is fully supported by commercial activities of SASU Teknoo Software
and EIRL Richard DELOGE. If you like it and help me maintain it and evolve it, don't hesitate to support me on
[Patreon](https://patreon.com/teknoo_software) or [Github](https://github.com/sponsors/TeknooSoftware).

Thanks :) Richard.

Credits
-------
EIRL Richard Déloge - <https://deloge.io> - Lead developer.
SASU Teknoo Software - <https://teknoo.software>

About Teknoo Software
---------------------
**Teknoo Software** is a PHP software editor, founded by Richard Déloge, as part of EIRL Richard Déloge.
Teknoo Software's goals : Provide to our partners and to the community a set of high quality services or software,
sharing knowledge and skills.

License
-------
East UX is licensed under the 3-Clause BSD License - see the [LICENSE](LICENSE) file for details.

Contribute :)
-------------
You are welcome to contribute to this project. [Fork it on Github](CONTRIBUTING.md)
