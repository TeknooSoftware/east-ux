# Components

East UX ships nine Symfony UX components. Their classes are in `infrastructures/symfony/Components/`
(namespace `Teknoo\East\UXBundle\Components\`), their templates in `infrastructures/symfony/templates/components/`
(Twig namespace `@TeknooEastUX`).

The bundle prepends `twig_component.defaults` with `Teknoo\East\UXBundle\Components\` mapped to
`@TeknooEastUX/components/`, so a component keeps its short name: `<twig:FormWindow ... />`,
`{{ component('FormWindow', {...}) }}`, and `FormWindow` renders `@TeknooEastUX/components/FormWindow.html.twig`.

Since the templates are in the `components/` directory of a bundle, Symfony UX also discovers them as anonymous
components: `bin/console debug:twig-component` lists `TeknooEastUX:FormWindow`, `TeknooEastUX:ListWindow`, ... next
to the class-based components. Never use these prefixed names: they render the template without its class (no
methods of `this`, no default values of the props, no live behaviour). Always use the short names.

| Component      | Kind | Class                         | Purpose                                                            |
|----------------|------|-------------------------------|--------------------------------------------------------------------|
| `Window`       | Twig | `Window` (`AbstractWindow`)   | Generic window: title bar, separator, pane; base of the other windows' templates |
| `FormWindow`   | Live | `FormWindow` (`AbstractWindow`, `LiveTrait`) | Window holding a Symfony form, as a modal (live) or as a full page |
| `ListWindow`   | Live | `ListWindow` (`AbstractWindow`, `LiveTrait`, `DefaultActionTrait`, `ComponentWithFormTrait`) | Paginated list of objects, optional search form, "New" button |
| `LoginWindow`  | Twig | `LoginWindow` (`AbstractWindow`) | Login form of Symfony's `form_login`                            |
| `MenuBar`      | Twig | `MenuBar`                     | Navigation bar built by the menu service ([menu.md](menu.md))      |
| `ObjectList`   | Twig | `ObjectList`                  | Table of objects, one item component (or block) per row            |
| `Pagination`   | Twig | `Pagination`                  | Page buttons driving the `page` prop of the enclosing Live Component |
| `ConfirmModal` | Twig | `ConfirmModal`                | Bootstrap confirmation modal styled as a window                     |
| `Card`         | Twig | `Card`                        | Bootstrap card with a title, a content and up to two links         |

`AbstractWindow` is not a component: it holds the props shared by the windows and the helpers `isClosable()`,
`isResizable()`, `isDraggable()` and `isFixed()` (draggable **and** closable).

The Live Components are re-rendered through the East recipe of their page: see [live-components.md](live-components.md).
Their Stimulus controllers are described in [stimulus.md](stimulus.md), the stylesheet, the form themes, the
translations and the template overrides in [theming.md](theming.md).

## Convention: public properties for the view parameters of the recipes

East Common's `LiveComponentBuilder` copies every view parameter of the recipe whose name matches a property of the
component (`objectInstance`, `objectsCollection`, `pageCount`, `page`, `formView`, `searchForm`, ...) by a direct
assignment from outside the class; since East Common 4.8, it ignores the properties that are not public and writable
from outside (private, protected, static, read-only, `private(set)`). A component therefore declares public and not
read-only the properties that receive a view parameter of the East recipes. The tests of each component check it.

In `ListWindow`, Symfony UX's `ComponentWithFormTrait` declares the private properties `form` and `formView`: East
Common leaves them alone, so a `ListWindow` may also be placed in a page rendered by `RenderForm`.

## Window props (`AbstractWindow`)

| Prop               | Type     | Default    | Use                                                                      |
|--------------------|----------|------------|--------------------------------------------------------------------------|
| `title`            | `string` | `''`       | Title of the title bar (displayed as given, not translated)              |
| `closable`         | `bool`   | `false`    | Shows the close button of the title bar                                   |
| `resizable`        | `bool`   | `false`    | Shows the resize button of the title bar                                  |
| `draggable`        | `bool`   | `false`    | Adds the class `draggable` and the `window-drag` controller on the title bar |
| `windowId`         | `string` | `''`       | Rendered as `data-form-window`, the identifier the Stimulus controllers look for |
| `closeRedirectUrl` | `string` | `''`       | Where `window-close` navigates when the window is closed (hidden when empty) |
| `closeAriaLabel`   | `string` | `'Close'`  | `aria-label` of the close button                                         |
| `resizeAriaLabel`  | `string` | `'Resize'` | `aria-label` of the resize button                                        |

The labels are props, so the application passes them already translated:
`closeAriaLabel="{{ 'app.aria.close'|trans }}"`.

## Window

```twig
<twig:Window title="{{ 'app.title.token'|trans }}" closable="1" closeRedirectUrl="{{ path('app_home') }}">
    <p>Content of the window</p>
</twig:Window>
```

The content given between the tags becomes the `content` block. Without content, the pane is empty. The root
element gets `data-controller="window-close[ window-drag]"`, `data-form-window` and
`data-window-close-redirect-url-value`.

Blocks of `Window.html.twig`, overridden by the templates extending it (`{% extends
'@TeknooEastUX/components/Window.html.twig' %}`): `window`, `window_classes`, `window_attributes`, `title_bar`,
`title_bar_classes`, `title_bar_attributes`, `closeAction` (the `data-action` of the close button), `separator`,
`separator_classes`, `separator_attributes`, `window_pane`, `window_pane_classes`, `window_pane_attributes`,
`content`, `window_modals` (after the pane, inside the window).

## FormWindow

A window holding a Symfony form, in two modes:

- `isLive` true (default): a modal driven by the `form-window` Stimulus controller, opened and closed in the page,
  saved with `fetch()`, re-rendered by its East recipe. This is the window that the "New" and "Edit" buttons load
  through the component renderer ([live-components.md](live-components.md#dynamic-form-windows)).
- `isLive` false: a full page (the `new` and `edit` pages of a CRUD), closed by `window-close`, which navigates to
  `closeRedirectUrl`.

```twig
{# Template of an East Common creation or edition route: `formView` and `objectInstance` are its view parameters #}
<twig:FormWindow
    title="{{ 'app.item.title.edit'|trans }}"
    windowId="item-{{ objectInstance.id }}"
    closable="1"
    hidden="0"
    isLive="0"
    formView="{{ formView }}"
    objectInstance="{{ objectInstance }}"
    formName="App\\Form\\Type\\ItemType"
    formPath="{{ path('app_item_edit', {'id': objectInstance.id}) }}"
    closeRedirectUrl="{{ path('app_item_list') }}"
/>
```

| Prop                 | Type                     | Live | Default                         | Use                                                            |
|----------------------|--------------------------|------|---------------------------------|----------------------------------------------------------------|
| window props         |                          | yes  |                                 | All the props of `AbstractWindow`, redeclared as LiveProps      |
| `objectId`           | `?string`                | yes (`url: true`) | `null`             | Identifier of the edited object, mapped in the URL of the re-renders |
| `formName`           | `?class-string`          | yes  | `null`                          | Form type, used to build the form when `formView` is not given |
| `formPath`           | `string`                 | yes  | `''`                            | `action` of the form, and the `originalPath` of the re-renders |
| `hidden`             | `bool`                   | yes (writable) | `true`                | Adds `d-none`; set `hidden="0"` on a full page                 |
| `isLive`             | `bool`                   | yes  | `true`                          | Modal driven by Stimulus, or full page                         |
| `formTheme`          | `?string`                | yes  | `teknoo_east_ux.form_theme`     | Form theme applied to the form; none when empty                |
| `originalPath`       | `?string`                | yes  | `formPath`                      | From East Foundation's `LiveTrait`, always derived from `formPath` |
| `formView`           | `?FormView`              | no   | `null`                          | View parameter of `RenderForm`, copied by the East bridge      |
| `objectInstance`     | `object\|string\|null`   | no   | `null`                          | View parameter of the recipes, copied by the East bridge       |

- `#[PostMount] postMount()` builds `formView` from `formName` and `objectInstance` when `formView` is null and
  `objectInstance` is set, and copies `formPath` into `originalPath`. `__invoke()`, the default action, calls it again
  on each live render.
- `#[LiveAction] close()` sets `hidden`. `hasSubForms()` tells whether a child of the form is compound.
- Rendering: compound children (embedded form types) each get a `<fieldset class="east-ux-fieldset">` titled with
  their label (translated in the default domain), the simple children are grouped in a first fieldset titled
  `teknoo.east.ux.form_window.fieldset.main`, hidden fields go to `form_rest()`. Without compound child, the form is
  rendered with `form_widget()`. The window gets `form-window-wide` when it has sub-forms, `form-fixed` when it is
  draggable and closable, `d-none` while hidden.
- The `<form>` has `data-form-window-target="form"`, `data-action="submit->form-window#submit"` and
  `data-skip-morph` (its values are replaced, not morphed, on a re-render); validation errors returned by the server
  are written in the `data-form-window-target="errors"` element.

## ListWindow

```twig
{# Template of an East Common list route: `objectsCollection`, `pageCount` and `page` are its view parameters #}
<twig:ListWindow
    title="{{ 'app.item.title.list'|trans }}"
    objectsCollection="{{ objectsCollection }}"
    pageCount="{{ pageCount }}"
    page="{{ page }}"
    itemComponent="ItemRow"
    tableHeaders="{{ ['app.item.table.name', 'app.item.table.actions'] }}"
    searchFormName="App\\Form\\Type\\ItemSearchType"
    newFormRoute="app_item_new"
    newFormName="App\\Form\\Type\\ItemType"
    newButtonLabel="app.item.action.new"
    closeAriaLabel="{{ 'app.aria.close'|trans }}"
/>
```

| Prop                | Type             | Live | Default | Use                                                                         |
|---------------------|------------------|------|---------|-----------------------------------------------------------------------------|
| window props        |                  | no   |         | Rendered once; `closeAriaLabel` is also given to the windows the "New" button opens |
| `objectsCollection` | `iterable<object>` | no | `[]`    | View parameter of `RenderList`, copied by the East bridge                   |
| `pageCount`         | `int`            | no   | `0`     | View parameter of `RenderList`; the pagination is rendered when it is above 1 |
| `page`              | `int`            | yes (writable, `url: true`) | `1` | Current page, updated by the `Pagination` buttons                  |
| `itemComponent`     | `string`         | yes  | `''`    | Component rendering a row, given `object` (usually an anonymous component)  |
| `tableHeaders`      | `array<string>`  | yes  | `[]`    | Translation keys of the headers, translated in the default domain          |
| `searchFormName`    | `?class-string`  | yes  | `null`  | Form type of the search form; no search form when empty                    |
| `newFormRoute`      | `string`         | yes  | `''`    | Route of the creation recipe; the "New" button is rendered only when set    |
| `newFormName`       | `string`         | yes  | `''`    | Form type of the window opened by the "New" button |
| `newButtonLabel`    | `string`         | yes  | `''`    | Translation key (default domain) of the "New" button; `teknoo.east.ux.list_window.action.new` when empty |
| `newFormTitle`      | `string`         | yes  | `''`    | Translation key (default domain) of the title of the opened window; the button label when empty |
| `originalPath`      | `?string`        | yes  | current path | From `LiveTrait`: the route whose recipe re-renders the list           |

- The search form is built by `ComponentWithFormTrait` (`instantiateForm()`), rendered with the Bootstrap 5 form theme
  and submitted with `data-action="live#action:prevent"` and `data-live-action-param="save"`: the trait resubmits it
  on the re-render, and East Common's `SearchFormLoader` replays it from the live payload in the recipe. Without
  `searchFormName`, the trait still needs a form: an empty one is used and never rendered (`hasSearchForm()`). A
  `searchFormName` that is not a class throws a `LogicException`.
- The "New" button carries the `form-window-dynamic-loader` controller with the URL of the component renderer
  (`path('_teknoo_east_ux_component_render')`: the application must import the bundle's routes), the window id
  `new-object-form`, `formPath` = `path(newFormRoute)` and `formName` = `newFormName`.
- The window renders the `ConfirmModal` `delete-confirm-modal` used by the `delete-item` controller of the rows, and
  carries the `list-window-refresh` controller (re-render on the `list-window:refresh` event).
- Helpers: `hasObjects()`, `hasPagination()`, `hasSearchForm()`, `getCurrentRequest()`.
- Blocks: `search_form`, `search_form_container_classes`, `objects_list`, `pagination`, `confirm_modals` (in
  `window_modals`), plus those of `Window`.

## LoginWindow

```twig
<twig:LoginWindow title="{{ 'app.title.login'|trans }}" actionPath="{{ path('app_login_check') }}" />
```

| Prop          | Type     | Default          | Use                                                                  |
|---------------|----------|------------------|----------------------------------------------------------------------|
| window props  |          |                  |                                                                      |
| `actionPath`  | `string` | `''`             | `action` of the form (the `check_path` of `form_login`)              |
| `rememberMe`  | `bool`   | `true`           | Renders the `_remember_me` checkbox                                  |
| `csrfTokenId` | `string` | `'authenticate'` | Token id of the `_csrf_token` field; no field when empty             |

The form posts `_username`, `_password`, `_remember_me` and `_csrf_token`, the field names of Symfony's
`form_login`. The last username and the last authentication error come from Symfony's `AuthenticationUtils`; the error
is translated from its message key and data in the `security` domain, in `<div id="login-error" class="alert
alert-danger" role="alert">`: the id is a stable hook for the tests of the applications.

## MenuBar

```twig
<twig:MenuBar :user="app.user ? app.user.wrappedUser : null">
    <twig:block name="brand">
        <span class="app-logo"></span> {{ 'app.name'|trans }}
    </twig:block>
</twig:MenuBar>
```

- Prop `user`: an East Common user (`Teknoo\East\Common\Contracts\User\UserInterface`), or null: without user, no
  section is rendered. With East Common's Symfony users, it is the `wrappedUser` of the security user.
- The brand links to the route `teknoo_east_ux.home_route`, or to `/` when it is not configured. Its block `brand`
  renders `teknoo.east.ux.menu_bar.brand.title` by default.
- The `Location::TopLeft` sections are rendered on the left (block `left_sections`), the `Location::TopRight` ones on
  the right (block `right_sections`), as Bootstrap dropdowns. See [menu.md](menu.md) for the generators, the
  dividers and the labels that are not translation keys.

## ObjectList

```twig
<twig:ObjectList objectsCollection="{{ objects }}" itemComponent="ItemRow" tableHeaders="{{ ['app.name'] }}" />

{# or with a block, which receives `object` #}
<twig:ObjectList objectsCollection="{{ objects }}">
    <twig:block name="list_item"><tr><td>{{ object.name }}</td></tr></twig:block>
</twig:ObjectList>
```

| Prop                | Type               | Default | Use                                                       |
|---------------------|--------------------|---------|-----------------------------------------------------------|
| `objectsCollection` | `iterable<object>` | `[]`    | Objects to list                                           |
| `itemComponent`     | `string`           | `''`    | Component rendered for each object, with the prop `object` |
| `tableHeaders`      | `array<string>`    | `[]`    | Translation keys of the headers (default domain); the last one is right-aligned |

When the collection is empty, an information alert renders `teknoo.east.ux.object_list.text.no_results`. Blocks:
`table_classes`, `table_attributes`, `table_header`, `tbody_attributes`, `list_items`, `list_item`,
`no_results_classes`, `no_results_attributes`.

An item component is usually an anonymous component of the application (`templates/components/anonymous/`), for
example:

```twig
{# templates/components/anonymous/ItemRow.html.twig #}
{% props object %}
<tr>
    <td>{{ object.name }}</td>
    <td class="text-end">
        <button type="button" class="btn btn-sm"
                data-controller="form-window-dynamic-loader"
                data-form-window-dynamic-loader-url-value="{{ path('_teknoo_east_ux_component_render') }}"
                data-form-window-dynamic-loader-window-id-value="item-{{ object.id }}"
                data-form-window-dynamic-loader-form-path-value="{{ path('app_item_edit', {'id': object.id}) }}"
                data-form-window-dynamic-loader-form-name-value="App\Form\Type\ItemType"
                data-form-window-dynamic-loader-object-id-value="{{ object.id }}"
                data-form-window-dynamic-loader-title-value="{{ object.name }}"
                data-action="click->form-window-dynamic-loader#open">{{ 'app.action.edit'|trans }}</button>
        <button type="button" class="btn btn-sm btn-danger"
                data-controller="delete-item"
                data-delete-item-url-value="{{ path('app_item_delete', {'id': object.id}) }}"
                data-delete-item-csrf-token-value="{{ csrf_token('app_delete_item') }}"
                data-delete-item-modal-id-value="delete-confirm-modal"
                data-action="click->delete-item#delete">{{ 'app.action.delete'|trans }}</button>
    </td>
</tr>
```

## Pagination

| Prop        | Type  | Default | Use                 |
|-------------|-------|---------|---------------------|
| `pageCount` | `int` | `0`     | Number of pages; nothing is rendered below 2 |
| `page`      | `int` | `1`     | Current page        |

The buttons use `data-model="page"` and `data-action="live#update"` with `data-value`: they update the `page` prop of
the enclosing Live Component (`ListWindow`), which is re-rendered by its recipe. `buildUrl(int $page)` returns the
query string of the current request with another page, for templates that prefer links. Blocks: `nav_classes`,
`nav_attributes`, `ul_classes`, `ul_attributes`.

## ConfirmModal

| Prop           | Type     | Default           |
|----------------|----------|-------------------|
| `modalId`      | `string` | `''`              |
| `title`        | `string` | `'Confirm'`       |
| `message`      | `string` | `'Are you sure?'` |
| `confirmText`  | `string` | `'Confirm'`       |
| `cancelText`   | `string` | `'Cancel'`        |
| `confirmClass` | `string` | `'btn-danger'`    |

A Bootstrap modal (`data-bs-backdrop="static"`) whose content is a window. Texts are props, already translated by the
caller. The confirm button carries `data-confirm-button`, which the `delete-item` controller listens to.

## Card

| Prop              | Type     | Default | Use                                                       |
|-------------------|----------|---------|-----------------------------------------------------------|
| `title`           | `string` | `''`    |                                                           |
| `content`         | `string` | `''`    | Escaped, line breaks converted to `<br />`                |
| `contentIsHtml`   | `bool`   | `false` | Renders `content` as HTML (line breaks still converted): only for a trusted content |
| `leftActionPath`  | `string` | `''`    | Left link, rendered with `leftActionText` when both are set |
| `leftActionText`  | `string` | `''`    |                                                           |
| `rightActionPath` | `string` | `''`    | Right link, rendered with `rightActionText` when both are set |
| `rightActionText` | `string` | `''`    |                                                           |
