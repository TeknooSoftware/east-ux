# Stimulus controllers

East UX ships ten Stimulus controllers, hand-written ES modules without build step, in `assets/dist/controllers/`.
They work next to the `live` controller of Symfony UX (see [live-components.md](live-components.md)). Every text shown
to the user and every protocol name is a Stimulus value with an English default, so an application translates or
adapts them from its templates.

## Loading the controllers

### With AssetMapper and StimulusBundle

`assets/package.json` declares the controllers for StimulusBundle (`symfony.controllers`, each with its short `name`)
and the importmap entries for Symfony Flex (`symfony.importmap`). The bundle maps `assets/dist/` in AssetMapper as
`@teknoo/east-ux` when AssetMapper is available.

When the library is installed or updated, Symfony Flex writes the `@teknoo/east-ux` entries into the application's
`assets/controllers.json` and `importmap.php`, **only if these files already exist** (commit them first, then require
the library and commit the entries Flex adds):

```json
{
    "controllers": {
        "@symfony/ux-live-component": {
            "live": {"enabled": true, "fetch": "eager", "autoimport": {"@symfony/ux-live-component/dist/live.min.css": true}}
        },
        "@teknoo/east-ux": {
            "form-window": {"enabled": true, "fetch": "eager"},
            "form-window-dynamic-loader": {"enabled": true, "fetch": "eager"},
            "window-dynamic-loader": {"enabled": true, "fetch": "eager"},
            "list-window-refresh": {"enabled": true, "fetch": "eager"},
            "window-close": {"enabled": true, "fetch": "eager"},
            "window-drag": {"enabled": true, "fetch": "eager"},
            "delete-item": {"enabled": true, "fetch": "eager"},
            "clipboard": {"enabled": true, "fetch": "eager"},
            "theme": {"enabled": true, "fetch": "eager"},
            "leave-confirm": {"enabled": true, "fetch": "eager"}
        }
    },
    "entrypoints": []
}
```

StimulusBundle registers them under their short identifiers (`form-window`, not `teknoo--east-ux--form-window`). Set
`enabled` to `false` for a controller the application does not use. Three controllers import
`@symfony/ux-live-component`, and `delete-item` and `leave-confirm` import `bootstrap`: both are entries of the importmap (the first one
from Symfony UX, the second one from East UX's `package.json`). The two loaders (`window-dynamic-loader`,
`form-window-dynamic-loader`) also import `../wait_cursor.js`, a module of the package that is not a controller
(`assets/dist/wait_cursor.js`, [the wait cursor](#the-wait-cursor)): AssetMapper resolves this relative import and
adds it to the importmap by itself.

The identifiers must stay the ones of this page: the templates of the components use them.

## Controllers

| Identifier                   | File                                         | Actions        | Targets                          | Values (default)                                                                                                  |
|------------------------------|----------------------------------------------|----------------|----------------------------------|-------------------------------------------------------------------------------------------------------------------|
| `form-window`                | `form_window_controller.js`                  | `open`, `close`, `submit` | `form`, `errors`, `submitButton` | `error-text` (`An error occurred while saving.`), `network-error-text` (`A network error occurred.`)      |
| `form-window-dynamic-loader` | `form_window_dynamic_loader_controller.js`   | `open`         | -                                | `url`, `window-id`, `form-path`, `form-name`, `title`, `closable` (`true`), `draggable` (`true`), `close-aria-label` (`Close`), `object-id`, `load-error-text` (`An error occurred while loading the form.`) |
| `window-dynamic-loader`      | `window_dynamic_loader_controller.js`        | `open`         | -                                | `url`, `component`, `title`, `props` (`{}`)                                                                       |
| `list-window-refresh`        | `list_window_refresh_controller.js`          | `refresh`, `open` | -                             | -                                                                                                                 |
| `window-close`               | `window_close_controller.js`                 | `close`        | -                                | `redirect-url`                                                                                                    |
| `window-drag`                | `window_drag_controller.js`                  | `startDrag`, `bringToFront` | -                   | -                                                                                                                 |
| `delete-item`                | `delete_item_controller.js`                  | `delete`       | -                                | `url`, `modal-id`, `csrf-token`, `csrf-field` (`_token`), `csrf-header` (`X-CSRF-Token`)                         |
| `clipboard`                  | `clipboard_controller.js`                    | `copy`         | -                                | `text`, `success-text` (`✓ Copied!`), `error-text` (`✗ Error`), `button-class` (`btn-primary`), `success-class` (`btn-success`), `error-class` (`btn-danger`) |
| `theme`                      | `theme_controller.js`                        | `select`, `toggleStyle`, `toggleMode` | `option`, `menuOption` | `style` (`legacy`), `mode` (`auto`), `storage` (`session`), `url`, `storage-key` (`east-ux-theme`), `modern-class` (`system-modern`), `switching-class` (`east-ux-theme-switching`) |
| `leave-confirm`              | `leave_confirm_controller.js`                | `confirm`      | -                                | `modal-id`                                                                                                        |

A value is set with `data-<identifier>-<value>-value`, for example
`data-form-window-error-text-value="{{ 'app.form.error'|trans }}"`. The templates of the bundle already set the
messages of `form-window` and the `load-error-text` of the "New" button of `ListWindow` from the `east_ux` catalogue
([theming.md](theming.md#translations)): the English defaults of the table only apply to the elements written by the
application.

### form-window

Attached by `FormWindow.html.twig` when the window is live (`data-controller="form-window[ window-drag] live"`, with
`data-form-window="<windowId>"`). The template sets `error-text` and `network-error-text` with
`attributes.defaults()`, translated from `teknoo.east.ux.form_window.text.error` and
`teknoo.east.ux.form_window.text.network_error`: an attribute given to `<twig:FormWindow>` by the application wins,
and a window loaded by the component renderer gets the translated messages too.

- `connect()` listens to the events `form-window:open` and `window:open` **on its own element** and calls `open()`;
  it also keeps the `style` of the window across the Live re-renders (see
  [the style of a Live window](#the-style-of-a-live-window)). `disconnect()` removes the listeners and the hooks.
- `open()`: re-renders the window (`getComponent(this.element)` then `component.render()`: the East recipe of
  `formPath` loads the object and builds the form), then removes `d-none`, hides the errors and dispatches
  `window:opened` on its element (not bubbling). If the response of this render is not `ok` or is a redirection (such
  as the login page of an expired session, followed by `fetch`) and the window has never been shown, the browser
  navigates to `originalPath`, the page of the form, without `window:opened`. If the re-render throws, the window is
  shown as it is, with `window:opened` too. When Live follows a redirection of the render itself (`X-Live-Redirect`,
  with its `Location`), the window is shown as it is while the page is left, without `window:opened`.
- `close()`: adds `d-none`, resets every `form` target and hides the errors. Bound to the close button of the title bar
  and to the Cancel button (`click->form-window#close`).
- `submit()` (bound on the form, `submit->form-window#submit`): sends the form with `fetch(form.action, {method,
  body: new FormData(form)})`, the submit button disabled meanwhile.
  - On a redirection (East Common's creation recipe after a save): `close()`, then dispatches `list-window:refresh`
    on `document`.
  - On another success (East Common answers 200 with the page of `formPath` after an edition, and when it refuses the
    form): the response is parsed with `DOMParser`, and the returned form is the `form[name="<name>"]` of the sent
    form, else the `form[action="<action>"]` (both escaped with `CSS.escape()`).
    - If the returned form has `data-form-window-invalid` (set by `FormWindow.html.twig`) or holds an
      `[aria-invalid="true"]` field: its content replaces the content of the sent `<form>` (fields, errors, tokens),
      the first `[aria-invalid="true"]` field gets the focus, and the window stays open.
    - Otherwise (the form is saved, or no returned form is found): `close()`, then dispatches `list-window:refresh`
      on `document`.
  - On a 400 or a 422: the response text is written (with `textContent`) in the `errors` target.
  - On another status: the `error-text` value; on a network failure: the `network-error-text` value.

### form-window-dynamic-loader

On the "New" button of `ListWindow`, whose `load-error-text` is translated from
`teknoo.east.ux.list_window.text.load_error`, and on the "Edit" buttons of the rows (see the item component example in
[components.md](components.md#objectlist)), whose values the application sets.

- `open()`: looks for `[data-form-window="<window-id>"]`. If the window exists, dispatches `form-window:open` on it.
  Otherwise, `POST`s to the `url` value (the component renderer, `path('_teknoo_east_ux_component_render')`) the JSON
  body `{"component": "FormWindow", "props": {windowId, formPath, originalPath (= formPath), formName, title,
  closable, draggable, closeAriaLabel, hidden: true[, objectId]}}` with the headers `Content-Type: application/json` and
  `X-Requested-With: XMLHttpRequest`, appends the returned HTML at the end of `#dynamic-modals`, waits one frame
  (`requestAnimationFrame` then `setTimeout(0)`) so that Stimulus connects the new `form-window` controller, and
  dispatches `form-window:open` on the new window.
- The `url` value is required: without it, or on any failure, the error goes to `console.error()` and the
  `load-error-text` value is shown with `alert()`.
- [The wait cursor](#the-wait-cursor) is shown from the click until the window is shown (`window:opened`). It ends
  before the `alert()` of a failure, and when `#dynamic-modals` or the window is not found after the loading.

### window-dynamic-loader

On a link that opens a window in the current page, without leaving it: a floating `ListWindow`, a `FormWindow`, any
Live window that the component renderer accepts ([live-components.md](live-components.md#dynamic-windows)). The
`href` of the link is the page of the window: it is opened by a click with the middle button or a modifier key, and
it is the fallback when the window can not be loaded.

```twig
{% set props = {'windowId': 'items', 'itemComponent': 'ItemRow', 'floating': true, 'closable': true,
    'draggable': true} %}
<a href="{{ path('app_item_list') }}"
   data-controller="window-dynamic-loader"
   data-window-dynamic-loader-url-value="{{ path('_teknoo_east_ux_component_render') }}"
   data-window-dynamic-loader-component-value="ListWindow"
   data-window-dynamic-loader-title-value="{{ 'app.item.title.list'|trans }}"
   data-window-dynamic-loader-props-value="{{ props|json_encode }}"
   data-action="click->window-dynamic-loader#open">{{ 'app.item.title.list'|trans }}</a>
```

Values:

- `url`: the URL of the component renderer (`path('_teknoo_east_ux_component_render')`), required;
- `component`: the name of the component to render (`ListWindow`, `FormWindow`, ...);
- `title`: the title of the window, added to the props when it is not empty (it replaces a `title` of `props`);
- `props` (`{}`): the props of the component, a JSON object; its `windowId` names the window in the page.

`open()` (bound to `click`):

- A click with another button than the main one, or with a modifier key (`Ctrl`, `Shift`, `Alt`, `Meta`), is left to
  the browser: it opens the `href` (in a new tab or window, ...). An element without `href` logs an error with
  `console.error()` and does nothing.
- When the page already holds the window of the link (`[data-form-window="<windowId>"]`, escaped with `CSS.escape()`)
  and this window is not live (it has neither `form-window` nor `list-window-refresh`; a `FormWindow` that is not live
  still carries the `live` controller of Symfony UX), such as the `FormWindow` of the page of the link, it can not be
  opened again: the click is left to the browser, which follows the link, without wait cursor (a
  [leave-confirm](#leave-confirm) container may ask before the page is left; see
  [the rules](#rules-for-the-templates-and-new-controllers)).
- Otherwise the navigation is prevented, and a click while its window is loaded from the renderer (until
  `window:open`) is ignored.
- The props are the `props` value, plus `originalPath` (the path and the query of the `href`: the recipe that the
  re-render of the window runs), `hidden: true`, and `title` when the `title` value is not empty. A `FormWindow`
  replaces `originalPath` with its `formPath` when it is mounted (`FormWindow::postMount()`): its re-render, and the
  page that `form-window` opens when this first render fails, are those of `formPath`, so give its link an `href`
  equal to `formPath`.
- If the page already holds the Live window (a window loaded before, or the window of the page of the link), nothing
  is loaded: it gets `window:open` and opens itself.
- Otherwise it `POST`s the JSON body `{"component": <component>, "props": <props>}` to the `url` value with the headers
  `Content-Type: application/json` and `X-Requested-With: XMLHttpRequest`, appends the returned HTML at the end of
  `#dynamic-modals`, and takes the `[data-form-window]` element it appended. It waits one frame
  (`requestAnimationFrame` then `setTimeout(0)`) so that Stimulus connects the controllers of the window (`live`
  included), sets the cascade offset on the window, then dispatches `window:open` on it: the Live window opens itself
  (`list-window-refresh` or `form-window`, whose re-render runs the recipe of `originalPath`, the `formPath` of a
  `FormWindow`), and `window-drag` brings it to the front.
- The cascade offset: `--east-ux-window-offset` (`style.setProperty()` on the root element of the window) is
  `(n % 6) * 1.5rem`, n being the number of the shown floating windows of the page
  (`.window.window-floating:not(.d-none), .window.form-window.form-fixed:not(.d-none)`; the new window is still hidden,
  so it is not counted). The windows opened one after the other do not cover each other
  ([theming.md](theming.md#floating-windows)), and the Live window keeps this offset across its re-renders
  ([the style of a Live window](#the-style-of-a-live-window)). A known limitation: a window still opening (its
  `window:open` dispatched, its first Live render not returned yet) is hidden too, so two links clicked within this
  time give the same offset to their windows, and the second covers the first until one of them is dragged.
- On any failure (no `url` value or no `#dynamic-modals`, a malformed `props` value, a network error, a response that
  is not `ok` or that is a redirection followed by `fetch`, such as the login page of an expired session, no window in
  the returned HTML), the error goes to `console.error()` and the browser navigates to the `href` of the link. There is
  no `alert()`, so no text to translate.

- [The wait cursor](#the-wait-cursor) is shown from the click (not for a click left to the browser or ignored)
  until the window is shown (`window:opened`). When the browser follows the link, it stays until the page is left.

`form-window-dynamic-loader` stays for the "New" and "Edit" buttons: it builds the props of a `FormWindow` from its
values and shows an `alert()` on failure.

### The wait cursor

From the click on a link or a button of `window-dynamic-loader` or `form-window-dynamic-loader` until the window is
shown, `<html>` carries `data-east-ux-waiting`, the number of the windows being opened, and the rule
`html[data-east-ux-waiting] *` of `east-ux.css` shows the `wait` cursor on the whole page
([theming.md](theming.md#stylesheet)). The two loaders share these waits through the module `wait_cursor.js`.

A wait ends:

- on the `window:opened` of its window, dispatched by `form-window` and `list-window-refresh` when their `open()`
  shows it: a Live render that answers, also refused or failed when the window has already been shown, and a window
  shown as it is when its render throws;
- before the `alert()` of `form-window-dynamic-loader`, and when it does not find the window it loaded;
- when the page is left (`pagehide`, listened by every loader): a page restored from the back-forward cache shows the
  normal cursor;
- after 10 seconds without any of them: a Live render that never answers (a network failure), a window that does not
  dispatch `window:opened`, a navigation that does not leave the page. An opening longer than that loses its cursor
  before the window is shown.

When the browser follows the link instead (a failure of the loading, a first render refused or redirected), or when
Live follows a redirection of a render itself (`X-Live-Redirect`, such as the login page of an expired session), the
cursor stays until the page is left. Two openings at the same time count 2, then 1, then the attribute is removed. A
second click on an entry whose window is still rendering re-renders it once more: both waits end when the window is
shown.

### list-window-refresh

Added to `ListWindow` (`attributes.defaults()`, with `window-close`, and `window-drag` when the window is draggable).

- `connect()` listens, before any `await`, to `list-window:refresh` on `document` and to `window:open` on its own
  element; it also keeps the `style` of the window across the Live re-renders (see
  [the style of a Live window](#the-style-of-a-live-window)). `disconnect()` removes the listeners and the hooks.
- `refresh()` gets the Live Component on demand and calls `component.render()` (the list is re-rendered by its East
  recipe, so a created or edited object appears). A window hidden with `d-none` (closed by the user, or not opened
  yet) is not refreshed: it stays closed, and is rendered again when it is opened.
- `open()` (on `window:open`) sets the writable LiveProp `hidden` to `false` (`component.set('hidden', false)`, without
  re-render) and calls `component.render()`, a single request: the East recipe of `originalPath` loads the list, and the
  window is rendered shown from now on. Then it removes `d-none` and dispatches `window:opened` on its element (not
  bubbling). If the response of this render is not `ok` (refused by the access rules of the route, server error, ...)
  or is a redirection (such as the login page of an expired session, followed by `fetch`) and the window has never
  been shown, the browser navigates to `originalPath`, the page of the window, instead of showing an empty window,
  without `window:opened`. A render that throws shows the window as it is, with `window:opened` too. When Live follows
  a redirection of the render itself (`X-Live-Redirect`, with its `Location`), the window is shown while the page is
  left, without `window:opened`.

### window-close

The default close action of `Window.html.twig` (`click->window-close#close`), also used by `ListWindow` and by
`FormWindow` when it is not live. `close()` navigates to the `redirect-url` value
(`data-window-close-redirect-url-value`, filled from the `closeRedirectUrl` prop) or, without it, hides the element with
`d-none`.

### window-drag

Added when the window is draggable; `startDrag` is bound on the title bar (`mousedown->window-drag#startDrag`). It
ignores the clicks on `.close` and `.resize`, converts the centred position of the window into absolute `left` and
`top` on the first drag, adds the class `dragging`, follows `mousemove` on `document` within the viewport and stops on
`mouseup`. The top of the window never goes above the bottom of the fixed menu bar (`.navbar.fixed-top`, the
`MenuBar`), or of the viewport without menu bar, even when the window is higher than the viewport: its title bar stays
reachable. Once dragged, the window is constrained to the viewport again each time its size changes (a
`ResizeObserver`) and at the end of each drag: a short list moved to the bottom of the screen stays on it when a
re-render makes it longer.

`connect()` also binds `bringToFront()` on the `mousedown`, `focusin`, `window:open` and `form-window:open` events of
the window. `bringToFront()` only acts on a floating window (`.window.window-floating` or
`.window.form-window.form-fixed`): it sorts the shown floating windows of the page by their `--east-ux-window-order`,
puts this one last and rewrites their orders from 1 to n (nothing is written when the window is already on top). The
closed windows (`d-none`) are not counted, so n stays under the cap of the `z-index` scale; the window itself is
counted even while it is still hidden, on `window:open`. The
`z-index` of `east-ux.css` follows the order ([theming.md](theming.md#floating-windows)). A floating window without
`window-drag` is never brought to the front: a floating `ListWindow` opened from a menu must be `draggable`, as a
`FormWindow` overlay is. `disconnect()` removes the listeners of the window and of `document`, and the
`ResizeObserver`.

### The style of a Live window

A Live re-render morphs the root element of the component with the HTML of the server, without re-applying the
changes made by JavaScript on this root element: its `style` (the position after a drag, the offset of the cascade,
the stacking order of `window-drag`) would be lost at each render (pagination, search, deletion, refresh, opening).
`form-window` and `list-window-refresh` keep it: in `connect()`, they register on the Live Component the hooks
`render:started` (saves the `style` attribute of the root element, before the morph) and `render:finished` (sets it
back, or removes it when there was none). They are registered in `connect()`, never when the window is opened, so the
first render keeps the cascade offset that `window-dynamic-loader` sets before `window:open`; `disconnect()` removes
them with `component.off()`. The other changes made by JavaScript on the root element are not kept: the class
`d-none` follows the server (`hidden`) and `open()`, the class `dragging` only lives during a drag.

### delete-item

On the "Delete" buttons of the rows:

```html
<button type="button"
        data-controller="delete-item"
        data-delete-item-url-value="{{ path('app_item_delete', {'id': object.id}) }}"
        data-delete-item-csrf-token-value="{{ csrf_token('app_delete_item') }}"
        data-delete-item-modal-id-value="delete-confirm-modal"
        data-action="click->delete-item#delete">
```

`delete()` opens the Bootstrap modal named by `modal-id` (`import { Modal } from 'bootstrap'`). `findModal()` looks for
it first in the closest `.window` of the button, then in the whole document: each `ListWindow` renders its own
`delete-confirm-modal`, so a row uses the modal of its window when several list windows are open. When it is shown, a
one-shot listener on its `[data-confirm-button]` element runs the deletion and hides the modal: a `POST` to `url`
carrying the `csrf-token` value in the form field named by `csrf-field` (`_token` by default) and in the header named by
`csrf-header` (`X-CSRF-Token` by default), with `X-Requested-With: XMLHttpRequest`. The field is the one East Common's
`CheckCsrfToken` reads on a delete route declaring the default `csrfTokenId` (East Common 4.8); the header serves an
application that checks it itself. The listeners of a confirmation are bound to the modal and removed when it is hidden
(`hidden.bs.modal`): the confirm button is shared by every row, and a cancelled confirmation never deletes its row on a
later confirmation. After a success or a redirection, the closest Live Component (`[data-controller~="live"]`, the
`ListWindow`) is re-rendered.

### clipboard

`copy()` writes the `text` value with `navigator.clipboard.writeText()` and gives a feedback on the button for two
seconds: the `success-class` and `success-text` values, or the `error-class` and `error-text` values on failure, in
place of the `button-class` value. Each class value may hold several classes separated by spaces, or none.

```html
<button type="button" class="btn btn-primary"
        data-controller="clipboard"
        data-clipboard-text-value="{{ token }}"
        data-clipboard-success-text-value="{{ 'app.copied'|trans }}"
        data-action="click->clipboard#copy">{{ 'app.copy'|trans }}</button>
```

### theme

Set on `<html>` by `east_ux_theme_attributes()`, with the values of the preference of the visitor. It switches the
style (class `system-modern`) and the mode (`data-bs-theme`) of the System.css x Bootstrap theme without reloading the
page. It keeps the choice in the localStorage (`browser` storage), sends it as JSON to the `url` value when it is set,
and dispatches `theme:changed` on `<html>`. The buttons of `ThemeSwitcher` and of `ThemeMenuGenerator` call `select`
with a `style` or `mode` param (`data-theme-mode-param="dark"`); they are its `option` targets (pressed buttons) and
`menuOption` targets (checked menu entries). See
[theme.md](theme.md#the-theme-controller).

### leave-confirm

Set by `MenuBar` on its `<nav>` (`click->leave-confirm#confirm`, merged with a `data-controller` and a `data-action`
given to `<twig:MenuBar>`). `modal-id` names the `ConfirmModal` that `MenuBar` renders after the `<nav>`
(`leave-confirm-modal`, texts `teknoo.east.ux.menu_bar.*`): inside the `<nav>`, the stacking context of `.fixed-top`
would keep it under the backdrop of Bootstrap.

Values:

- `modal-id`: the id of the confirmation modal, a `ConfirmModal` with its `[data-confirm-button]`.

`confirm()` (bound to `click` on the container of the links, so it runs after the actions of the clicked link):

- The click is left to the browser, without confirmation, when:
  - it is already handled (`event.defaultPrevented`): an entry opening a window (`window-dynamic-loader`), a toggle of
    Bootstrap (its delegated handlers run on `document` in the capture phase), a link whose own action prevents it;
  - it is made with another button than the main one, or with a modifier key (`Ctrl`, `Shift`, `Alt`, `Meta`);
  - it is not on a link (`a[href]`, the closest one of the target, so an icon of the link counts), such as the
    buttons of the theme;
  - the link opens elsewhere or stays in the page: a `target` other than `_self`, a `download`, an `href` starting
    with `#`;
  - no floating window is shown (the selector of the cascade of `window-dynamic-loader`): the window of the page is
    not counted;
  - the modal named by `modal-id`, or its `[data-confirm-button]`, is not in the page.
- Otherwise the navigation is prevented and the modal is shown (`import { Modal } from 'bootstrap'`). Its confirm
  button hides it, and the link is followed (`window.location.assign()`) once it is hidden (`hidden.bs.modal`), so a
  page restored from the back-forward cache shows no modal. Cancel hides it and the page stays. The listeners of a
  confirmation are removed when the modal is hidden, as for `delete-item`.
- A link activated with `Enter` is handled the same way. The dropdown of the section closes (Bootstrap closes it on
  any click on an entry). The modal is static: `Escape` does not close it. The focus is not given back to the menu
  after a cancel.
- Limitations: the page is left without confirmation by a window that is still opening (hidden, so not counted), the
  fallbacks of the loaders to the link after a failure, the close box of a window with a `redirect-url`, the submit
  of a form that is not in a Live window, the links outside the container, and the navigation of the browser itself
  (back, reload, address bar: there is no `beforeunload` handler).

## Contract with the page

The controllers expect the page to provide:

| Element or behaviour                         | Used by                        | Provided by                                                                 |
|----------------------------------------------|--------------------------------|-----------------------------------------------------------------------------|
| `<div id="dynamic-modals"></div>`            | `form-window-dynamic-loader`, `window-dynamic-loader` | The application's layout, once per page: loaded windows are appended to it |
| The modal `#delete-confirm-modal` and its `[data-confirm-button]` | `delete-item` (`modal-id` value) | `ListWindow` (a `ConfirmModal`); another modal can be named with `modal-id` |
| The modal `#leave-confirm-modal` and its `[data-confirm-button]` | `leave-confirm` (`modal-id` value) | `MenuBar` (a `ConfirmModal` after its `<nav>`); without it, the links are followed |
| The shown floating windows (`.window.window-floating:not(.d-none)`, `.window.form-window.form-fixed:not(.d-none)`) | `window-dynamic-loader` (cascade), `leave-confirm` | `ListWindow` (`floating`), `FormWindow` (closable and draggable) |
| The event `list-window:refresh` on `document` | `list-window-refresh`         | `form-window` after a save; the application may dispatch it too             |
| The event `form-window:open` on a window element | `form-window`               | `form-window-dynamic-loader`; the application may dispatch it to open a window |
| The event `window:open` on a window element  | `form-window`, `list-window-refresh` | `window-dynamic-loader`; the application may dispatch it to open a Live window (`FormWindow` when it is live, `ListWindow`) |
| The event `window:opened` on a window element | `form-window-dynamic-loader`, `window-dynamic-loader` (end of the wait cursor) | `form-window`, `list-window-refresh`; a custom Live window opened by a loader must dispatch it, else its wait cursor lasts 10 seconds |
| The fixed menu bar `.navbar.fixed-top`       | `window-drag` (top limit of a drag) | `MenuBar`; without it, the limit is the top of the viewport          |
| The URL of the component renderer            | `form-window-dynamic-loader`, `window-dynamic-loader` (`url` value) | The bundle's route `_teknoo_east_ux_component_render`, imported by the application |
| `X-Requested-With: XMLHttpRequest`           | sent by `form-window-dynamic-loader`, `window-dynamic-loader` and `delete-item` | The application may require it on its routes |
| CSRF token in the field `csrf-field` and the header `csrf-header` | sent by `delete-item` | The application generates the token (`csrf-token` value) and checks it on its delete routes (East Common: the route default `csrfTokenId`) |
| Bootstrap's JavaScript (`Modal`)             | `delete-item`, `leave-confirm` | The `bootstrap` entry of the importmap                                      |
| `<html {{ east_ux_theme_attributes() }}>`    | `theme`                        | The application's layout (see [theme.md](theme.md#the-page))                |
| The rule of `html.east-ux-theme-switching`   | `theme` (no fade while switching) | `east-ux.css`                                                            |
| The rule of `html[data-east-ux-waiting]`     | `form-window-dynamic-loader`, `window-dynamic-loader` (wait cursor) | `east-ux.css`                          |

| Event                 | Dispatched on                    | By                           | Listened by (on)                       |
|-----------------------|----------------------------------|------------------------------|----------------------------------------|
| `form-window:open`    | the `[data-form-window]` element | `form-window-dynamic-loader` | `form-window` and `window-drag` (its own element) |
| `window:open`         | the `[data-form-window]` element | `window-dynamic-loader`, the application | `form-window`, `list-window-refresh` and `window-drag` (its own element) |
| `window:opened`       | the `[data-form-window]` element, not bubbling | `form-window`, `list-window-refresh` when `open()` shows the window | `form-window-dynamic-loader`, `window-dynamic-loader` (the window), the application |
| `list-window:refresh` | `document`                       | `form-window` after a save   | `list-window-refresh` (`document`)     |
| `theme:changed`       | `<html>`                         | `theme` after a change       | The application, to persist the choice itself |

## Rules for the templates and new controllers

- From a Live Component template, add a controller with `attributes.defaults({'data-controller': '...'})`, never with
  a second `data-controller` attribute.
- Wait one frame after `insertAdjacentHTML()` before dispatching an event to an injected element: Stimulus connects
  the new controllers through a `MutationObserver`.
- Get a Live Component on demand (`await getComponent(element)` in the action): an event fired while
  `connect()` awaits it would be lost. Register its hooks in `connect()` (`getComponent(element).then(...)`, after
  the listeners) and remove them in `disconnect()`.
- `window:open` is handled only by the Live windows (`form-window`, `list-window-refresh`): dispatched on a `Window` or
  on a `FormWindow` that is not live, it opens nothing (`window-drag` only brings it to the front). So
  `window-dynamic-loader` reuses only a window with one of these two controllers (not one with only `live`, which a
  `FormWindow` that is not live still carries): for another window with the same `windowId`, such as the `FormWindow`
  of the page of its link (its fallback page), it leaves the click to the browser, which follows the link.
- A controller that opens a window on `window:open` or `form-window:open` dispatches `window:opened` on it once it is
  shown (`new CustomEvent('window:opened')`, not bubbling), not when it navigates instead or when Live follows a
  redirection of its render (`Location` header of the response): the loaders end their wait cursor on it, else after
  10 seconds.
- Bind the listeners of `document` or `window` in `connect()` and remove them in `disconnect()`.
- Keep the texts out of the JavaScript: use values with an English default.
