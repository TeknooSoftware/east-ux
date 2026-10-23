# Theming

The components are built on Bootstrap 5 classes. East UX adds a structural stylesheet, a form theme setting, an
English translation catalogue, and every template can be overridden: an application gives them its own look without
changing the library.

## Stylesheet

`assets/dist/styles/east-ux.css` holds only structural rules and a minimal neutral rendering, so that the components
display without a theme. Colours come from the Bootstrap 5.3 CSS variables, with neutral fallbacks.

| Selector                                                  | Rule                                                                    |
|-----------------------------------------------------------|-------------------------------------------------------------------------|
| `.window`                                                 | Bordered box, column layout                                             |
| `.title-bar`, `.title-bar .title`, `.title-bar button.close`, `.title-bar button.resize` | Title bar with its centred title, close and resize buttons (drawn with `×` and `□`) |
| `.title-bar .hidden`                                      | A hidden title-bar button keeps its place (Bootstrap 5 has no `.hidden`) |
| `.separator`                                              | Line between the title bar and the pane                                 |
| `.window-pane`                                            | Content of the window, scrolling when the window is height-constrained  |
| `.menu-icon`                                              | Size and alignment of the menu icons (the icon set stays in the application) |
| `.window.form-window`, `.form-fixed`, `.form-window-wide` | `FormWindow` overlay: centred, fixed, wide when the form has sub-forms   |
| `.form-window-subforms`, `.east-ux-fieldset`              | Sub-form fieldsets of `FormWindow`                                      |
| `.window.draggable .title-bar`, `.window.draggable.dragging` | Cursor and state while dragging                                      |
| `.modal .window.confirm-modal`                            | Window rendering of `ConfirmModal`                                      |
| `.dropdown-item[data-theme-target~="menuOption"]`         | Check mark of the current theme entries of the menu bar                 |
| `html.east-ux-theme-switching *`                          | No transition while the `theme` controller switches the theme           |

With AssetMapper, the stylesheet is an entry of the importmap (`@teknoo/east-ux/styles/east-ux.css`, written by Flex)
imported by the application's `assets/app.js`, after Bootstrap and before its own stylesheet:

```js
// assets/app.js
import './stimulus_bootstrap.js';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@teknoo/east-ux/styles/east-ux.css';
import './styles/app.css';
```

With npm, import `@teknoo/east-ux/dist/styles/east-ux.css` from the application's entry point. The application restyles
the components in its own stylesheet, loaded after this one.

### With a window theme

A theme that draws the windows itself (it styles `.window`, `.title-bar`, `.title`, `.close`, `.resize`, `.separator`
and `.window-pane`) is loaded after `east-ux.css` and wins wherever it sets a property. The neutral rendering also
sets properties that such a theme often leaves unset; they still apply, and the application resets in its own
stylesheet those its theme does not want:

| Selector                                                  | Property set by `east-ux.css`                              | Reset, when the theme does not want it         |
|-----------------------------------------------------------|------------------------------------------------------------|------------------------------------------------|
| `.title-bar`                                              | `gap: 0.5rem`                                              | `gap: normal`                                  |
| `.title-bar .title`                                       | `flex: 1 1 auto` (the title fills the bar), `white-space: nowrap` with an ellipsis | `flex: 0 1 auto`, `white-space: normal` |
| `.title-bar button`                                       | `opacity: 0.6` (`1` on hover and focus), `border-radius`, `font-size`, `width` and `height` | `opacity: 1`, the theme's sizes |
| `.title-bar button.close::before`, `.title-bar button.resize::before` | `content: "×"` and `content: "□"` (the glyphs of the buttons) | `content: none` when the theme draws the buttons with a background |
| `.window.form-window`, `.modal .window.confirm-modal`     | `box-shadow: var(--bs-box-shadow, …)` (a soft shadow)      | The theme's shadow                             |
| `.window.form-window.form-fixed`                          | `max-height: calc(100vh - 2rem)`                           | The theme's limit                              |

For example, a retro theme whose title bar has stripes on both sides of the title and whose buttons are drawn with
background images:

```css
/* After east-ux.css and the theme */
.title-bar { gap: normal; }
.title-bar .title { flex: 0 1 auto; }
.title-bar button { opacity: 1; }
.title-bar button.close::before,
.title-bar button.resize::before { content: none; }
.window.form-window,
.modal .window.confirm-modal { box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.5); }
```

### The System.css x Bootstrap theme

East UX ships the System.css x Bootstrap theme, with its fonts and the helpers that let visitors switch its style and
its mode: see [theme.md](theme.md). The theme draws the windows itself; the reset of the previous section applies.

## Form theme

`FormWindow` applies its `formTheme` prop to its form with `{% form_theme %}`. The default value of the prop is the
`form_theme` key of the bundle configuration:

```yaml
# config/packages/teknoo_east_ux.yaml
teknoo_east_ux:
    form_theme: '@App/form/fields.html.twig'
```

Without this key (and without prop), no theme is set on the form, and the global form themes of the application
(`twig.form_themes`) apply. A page may pass another theme: `<twig:FormWindow formTheme="bootstrap_5_layout.html.twig"
... />`. The search form of `ListWindow` uses
`bootstrap_5_layout.html.twig`; override the template of `ListWindow` (block `search_form`) to change it.

## Templates

Every template of the bundle can be overridden by the application, with Symfony's bundle override mechanism:

| Template of the bundle                                  | Override in the application                                                |
|---------------------------------------------------------|----------------------------------------------------------------------------|
| `@TeknooEastUX/components/<Name>.html.twig`             | `templates/bundles/TeknooEastUXBundle/components/<Name>.html.twig`        |
| `@TeknooEastUX/component_renderer.html.twig`            | `templates/bundles/TeknooEastUXBundle/component_renderer.html.twig`       |

An override may extend the original with `{% extends '@!TeknooEastUX/components/MenuBar.html.twig' %}` (the `!` skips
the overrides) and redefine some blocks only. For a single page, the blocks of a component are overridden where it is
used:

```twig
<twig:MenuBar :user="user">
    <twig:block name="brand">
        <span class="app-logo"></span> {{ 'app.name'|trans }}
    </twig:block>
</twig:MenuBar>
```

The blocks of each component are listed in [components.md](components.md). The window templates extend
`@TeknooEastUX/components/Window.html.twig`: an override of `Window.html.twig` changes all the windows.

## Translations

The texts of the templates are translation keys `teknoo.east.ux.<component>.<group>.<id>` of the domain **`east_ux`**,
always passed explicitly to the `trans` filter. The bundle ships an English catalogue
(`infrastructures/symfony/translations/east_ux.en.yaml`, read by the translator from the bundle's directory). An
application translates them in another language, or changes the English texts, in its own
`translations/east_ux.<locale>.yaml`.

| Key                                                | English                                                                  |
|----------------------------------------------------|--------------------------------------------------------------------------|
| `teknoo.east.ux.form_window.fieldset.main`         | General Information                                                      |
| `teknoo.east.ux.form_window.action.save`           | Save                                                                     |
| `teknoo.east.ux.form_window.action.cancel`         | Cancel                                                                   |
| `teknoo.east.ux.form_window.text.error`            | An error occurred while saving.                                          |
| `teknoo.east.ux.form_window.text.network_error`    | A network error occurred.                                                |
| `teknoo.east.ux.list_window.action.new`            | New                                                                      |
| `teknoo.east.ux.list_window.action.delete`         | Delete                                                                   |
| `teknoo.east.ux.list_window.action.cancel`         | Cancel                                                                   |
| `teknoo.east.ux.list_window.modal.delete_title`    | Confirm Deletion                                                         |
| `teknoo.east.ux.list_window.modal.delete_message`  | Are you sure you want to delete this item? This action cannot be undone. |
| `teknoo.east.ux.list_window.text.load_error`       | An error occurred while loading the form.                                |
| `teknoo.east.ux.login_window.text.error_prefix`    | Error:                                                                   |
| `teknoo.east.ux.login_window.input.username`       | Username                                                                 |
| `teknoo.east.ux.login_window.input.password`       | Password                                                                 |
| `teknoo.east.ux.login_window.input.remember_me`    | Remember me                                                              |
| `teknoo.east.ux.login_window.button.sign_in`       | Sign In                                                                  |
| `teknoo.east.ux.object_list.text.no_results`       | No results found                                                         |
| `teknoo.east.ux.pagination.text.pagination`        | Pagination                                                               |
| `teknoo.east.ux.pagination.action.previous`        | Previous                                                                 |
| `teknoo.east.ux.pagination.action.next`            | Next                                                                     |
| `teknoo.east.ux.menu_bar.brand.title`              | Home                                                                     |
| `teknoo.east.ux.theme_switcher.group.theme`        | Theme                                                                    |
| `teknoo.east.ux.theme_switcher.group.mode`         | Appearance                                                               |
| `teknoo.east.ux.theme_switcher.group.style`        | Style                                                                    |
| `teknoo.east.ux.theme_switcher.mode.light`         | Day                                                                      |
| `teknoo.east.ux.theme_switcher.mode.dark`          | Night                                                                    |
| `teknoo.east.ux.theme_switcher.mode.auto`          | Automatic                                                                |
| `teknoo.east.ux.theme_switcher.style.legacy`       | Classic                                                                  |
| `teknoo.east.ux.theme_switcher.style.modern`       | Modern                                                                   |

The `teknoo.east.ux.theme_switcher.*` keys label `ThemeSwitcher` and the entries added to the menu bar by
`ThemeMenuGenerator`.

Labels supplied by the application keep **its default domain**: the entries of the menu (unless their
`translationDomain` is set), the headers of `ObjectList`
and `ListWindow` (`tableHeaders`), the labels of the sub-forms of `FormWindow`, `newButtonLabel` and `newFormTitle` of
`ListWindow`. The authentication errors of `LoginWindow` are translated in Symfony's `security` domain. The other
texts given as props (titles, ARIA labels, texts of `ConfirmModal` and `Card`) are rendered as given: the application
translates them before passing them. The texts of the Stimulus controllers are values ([stimulus.md](stimulus.md)):
the templates of the bundle fill the messages of `form-window` (`FormWindow`, when it is live) and the
`load-error-text` of the "New" button of `ListWindow` from the keys `teknoo.east.ux.form_window.text.*` and
`teknoo.east.ux.list_window.text.load_error`; the other texts keep their English default unless the application sets
them.
