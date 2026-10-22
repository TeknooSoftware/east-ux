# Stimulus controllers

East UX ships seven Stimulus controllers, hand-written ES modules without build step, in `assets/dist/controllers/`.
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
            "list-window-refresh": {"enabled": true, "fetch": "eager"},
            "window-close": {"enabled": true, "fetch": "eager"},
            "window-drag": {"enabled": true, "fetch": "eager"},
            "delete-item": {"enabled": true, "fetch": "eager"},
            "clipboard": {"enabled": true, "fetch": "eager"}
        }
    },
    "entrypoints": []
}
```

StimulusBundle registers them under their short identifiers (`form-window`, not `teknoo--east-ux--form-window`). Set
`enabled` to `false` for a controller the application does not use. Three controllers import
`@symfony/ux-live-component` and `delete-item` imports `bootstrap`: both are entries of the importmap (the first one
from Symfony UX, the second one from East UX's `package.json`).

### With npm

The same `assets/package.json` is a valid npm package. An application still on npm (it has a `package.json` and no
`importmap.php`) depends on it and registers the controllers itself:

```json
{
    "dependencies": {
        "@teknoo/east-ux": "file:vendor/teknoo/east-ux/assets"
    }
}
```

Symfony Flex writes this line when the library is installed or updated: in `devDependencies` when the key is
missing, and it keeps the key where it already is. Move it to `dependencies`: the browser loads the controllers at
runtime, and an `npm install --omit=dev` would skip them. Flex also writes the seven controllers into
`assets/controllers.json` if that file exists; they only matter to a front end that reads it (StimulusBundle or
`@symfony/stimulus-bridge`). Run `npm install` after Composer, since the package is a link into `vendor/`.

```js
import { Application } from '@hotwired/stimulus';
import LiveController from '@symfony/ux-live-component';
import FormWindowController from '@teknoo/east-ux/dist/controllers/form_window_controller.js';
import FormWindowDynamicLoaderController from '@teknoo/east-ux/dist/controllers/form_window_dynamic_loader_controller.js';
// ... one import per controller

const application = Application.start();
application.register('live', LiveController);
application.register('form-window', FormWindowController);
application.register('form-window-dynamic-loader', FormWindowDynamicLoaderController);
// ...
```

Without a bundler, an application serving `node_modules` through a native import map adds a prefix entry for the
package, next to `@hotwired/stimulus`, `@symfony/ux-live-component` and `bootstrap` (imported by the controllers):

```html
<script type="importmap">
{
    "imports": {
        "@teknoo/east-ux/": "/js/node_modules/@teknoo/east-ux/"
    }
}
</script>
```

The identifiers must stay the ones of this page: the templates of the components use them.

## Controllers

| Identifier                   | File                                         | Actions        | Targets                          | Values (default)                                                                                                  |
|------------------------------|----------------------------------------------|----------------|----------------------------------|-------------------------------------------------------------------------------------------------------------------|
| `form-window`                | `form_window_controller.js`                  | `open`, `close`, `submit` | `form`, `errors`, `submitButton` | `error-text` (`An error occurred while saving.`), `network-error-text` (`A network error occurred.`)      |
| `form-window-dynamic-loader` | `form_window_dynamic_loader_controller.js`   | `open`         | -                                | `url`, `window-id`, `form-path`, `form-name`, `title`, `closable` (`true`), `draggable` (`true`), `close-aria-label` (`Close`), `object-id`, `load-error-text` (`An error occurred while loading the form.`) |
| `list-window-refresh`        | `list_window_refresh_controller.js`          | `refresh`      | -                                | -                                                                                                                 |
| `window-close`               | `window_close_controller.js`                 | `close`        | -                                | `redirect-url`                                                                                                    |
| `window-drag`                | `window_drag_controller.js`                  | `startDrag`    | -                                | -                                                                                                                 |
| `delete-item`                | `delete_item_controller.js`                  | `delete`       | -                                | `url`, `modal-id`, `csrf-token`, `csrf-field` (`_token`), `csrf-header` (`X-CSRF-Token`)                         |
| `clipboard`                  | `clipboard_controller.js`                    | `copy`         | -                                | `text`, `success-text` (`✓ Copied!`), `error-text` (`✗ Error`), `button-class` (`btn-primary`), `success-class` (`btn-success`), `error-class` (`btn-danger`) |

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

- `connect()` listens to the event `form-window:open` **on its own element** and calls `open()`.
- `open()`: re-renders the window (`getComponent(this.element)` then `component.render()`: the East recipe of
  `formPath` loads the object and builds the form), then removes `d-none` and hides the errors. If the re-render fails,
  the window is shown as it is.
- `close()`: adds `d-none`, resets every `form` target and hides the errors. Bound to the close button of the title bar
  and to the Cancel button (`click->form-window#close`).
- `submit()` (bound on the form, `submit->form-window#submit`): sends the form with `fetch(form.action, {method,
  body: new FormData(form)})`, the submit button disabled meanwhile.
  - On a success or a redirection: `close()`, then dispatches `list-window:refresh` on `document`.
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

### list-window-refresh

Added to `ListWindow` (`attributes.defaults({'data-controller': 'list-window-refresh'})`). `connect()` awaits
`getComponent(this.element)`, then listens to `list-window:refresh` on `document`; `refresh()` calls
`component.render()` (the list is re-rendered by its East recipe, so a created or edited object appears).
`disconnect()` removes the listener.

### window-close

The default close action of `Window.html.twig` (`click->window-close#close`), also used by `FormWindow` when it is not
live. `close()` navigates to the `redirect-url` value (`data-window-close-redirect-url-value`, filled from the
`closeRedirectUrl` prop) or, without it, hides the element with `d-none`.

### window-drag

Added when the window is draggable; `startDrag` is bound on the title bar (`mousedown->window-drag#startDrag`). It
ignores the clicks on `.close` and `.resize`, converts the centred position of the window into absolute `left` and
`top` on the first drag, adds the class `dragging`, follows `mousemove` on `document` within the viewport and stops on
`mouseup`. `disconnect()` removes the listeners of `document`.

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

`delete()` opens the Bootstrap modal named by `modal-id` (`import { Modal } from 'bootstrap'`). When it is shown, a
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

## Contract with the page

The controllers expect the page to provide:

| Element or behaviour                         | Used by                        | Provided by                                                                 |
|----------------------------------------------|--------------------------------|-----------------------------------------------------------------------------|
| `<div id="dynamic-modals"></div>`            | `form-window-dynamic-loader`   | The application's layout, once per page: loaded windows are appended to it  |
| The modal `#delete-confirm-modal` and its `[data-confirm-button]` | `delete-item` (`modal-id` value) | `ListWindow` (a `ConfirmModal`); another modal can be named with `modal-id` |
| The event `list-window:refresh` on `document` | `list-window-refresh`         | `form-window` after a save; the application may dispatch it too             |
| The event `form-window:open` on a window element | `form-window`               | `form-window-dynamic-loader`; the application may dispatch it to open a window |
| The URL of the component renderer            | `form-window-dynamic-loader` (`url` value) | The bundle's route `_teknoo_east_ux_component_render`, imported by the application |
| `X-Requested-With: XMLHttpRequest`           | sent by `form-window-dynamic-loader` and `delete-item` | The application may require it on its routes |
| CSRF token in the field `csrf-field` and the header `csrf-header` | sent by `delete-item` | The application generates the token (`csrf-token` value) and checks it on its delete routes (East Common: the route default `csrfTokenId`) |
| Bootstrap's JavaScript (`Modal`)             | `delete-item`                  | The `bootstrap` entry of the importmap                                      |

| Event                 | Dispatched on                    | By                           | Listened by (on)                       |
|-----------------------|----------------------------------|------------------------------|----------------------------------------|
| `form-window:open`    | the `[data-form-window]` element | `form-window-dynamic-loader` | `form-window` (its own element)        |
| `list-window:refresh` | `document`                       | `form-window` after a save   | `list-window-refresh` (`document`)     |

## Rules for the templates and new controllers

- From a Live Component template, add a controller with `attributes.defaults({'data-controller': '...'})`, never with
  a second `data-controller` attribute.
- Wait one frame after `insertAdjacentHTML()` before dispatching an event to an injected element: Stimulus connects
  the new controllers through a `MutationObserver`.
- Get a Live Component on demand (`await getComponent(element)` in the action): an event fired while
  `connect()` awaits it would be lost. `list-window-refresh` is the exception, guarded by `if (this.component)`.
- Bind the listeners of `document` or `window` in `connect()` and remove them in `disconnect()`.
- Keep the texts out of the JavaScript: use values with an English default.
