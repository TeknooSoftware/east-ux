# Teknoo Software - East UX - Change Log

## [0.1.0] - 2026-10-22
### Alpha Release

The application-level UI pieces of an East application, extracted from an application built on East Foundation and
East Common, into a Symfony bundle (`Teknoo\East\UXBundle\TeknooEastUXBundle`) and universal code (`Teknoo\East\UX\`).
Requires East Foundation 9.4 (the access rules of the route of `originalPath` checked on a Live re-render, a string
prop starting with `@` kept as is) and East Common 4.8 (the CSRF check of the delete routes, a `ListWindow` usable in
a page rendered by `RenderForm`).

#### Components
- Twig Components `Window`, `LoginWindow`, `MenuBar`, `ObjectList`, `Pagination`, `ConfirmModal` and `Card`, Live
  Components `FormWindow` and `ListWindow`, and their base class `AbstractWindow`, with their templates in the Twig
  namespace `@TeknooEastUX`. The bundle prepends `twig_component.defaults`, so the components keep their short names.
- Templates overridable in `templates/bundles/TeknooEastUXBundle/`.
- Translation keys `teknoo.east.ux.*` in the `east_ux` domain, with an English catalogue, the domain always passed
  explicitly; labels supplied by the application (menu entries, list headers, sub-form labels) keep its default
  domain.
- `FormWindow`: new `formTheme` prop, its default from the `form_theme` key of the bundle configuration (none: the
  global form themes apply).
- `ListWindow`: the search form is optional; new `newButtonLabel` and `newFormTitle` props; the "New" button is
  rendered only when `newFormRoute` is set, and gives the URL of the component renderer to its Stimulus controller.
- `LoginWindow`: new `rememberMe` and `csrfTokenId` props; authentication errors translated from their message key and
  data in the `security` domain, in an alert with the id `login-error`.
- `MenuBar`: a Twig Component taking an East Common user as `user` prop, an overridable `brand` block linked to the
  `home_route` of the bundle configuration, the `Location::TopLeft` sections on the left and the `Location::TopRight`
  sections on the right; every entry comes from the menu generators of the application.
- `Card`: `content` is escaped (line breaks still converted), unless the new `contentIsHtml` prop is set.
- `Window` declares `strict_types`, and renders without content.

#### Component renderer
- Controller `ComponentRenderer`, route `_teknoo_east_ux_component_render` (`POST /render`, imported with a prefix
  such as `/component`), rendering a component requested by the `form-window-dynamic-loader` Stimulus controller.
- The JSON body holds `component` and `props`: the component is rendered with these props; a body that is not a JSON
  object, or without `component` or `props`, gets a 400 `Missing component or props`.
- Validation of the props, East oriented (a promise, no returned value): `PropsValidatorInterface`, validators tagged
  `teknoo.east.ux.component_rendering.validation` with the class of their component (attribute `component`), gathered
  by `PropsValidatorRegistry`, which ignores a component without validator when
  `component_rendering.allow_unreferenced_components` is `true`. Invalid props get a 400 (`InvalidPropsException`), a
  component without validator a 403 (`UnreferencedComponentException`). `FormWindowPropsValidator` validates the
  props of `FormWindow`.

#### Menu
- `MenuGeneratorInterface`, `MenuService`, `MenuSection`, `MenuItem`, `Location` and `Sorting`; generators tagged
  `teknoo.east.ux.menu.generator`.
- `MenuService` keeps its cache in the instance and implements `ResetInterface` (tag `kernel.reset`): a menu is not
  kept between two requests of a worker, or between two tests.
- An appended section is positioned after the sections of its location (not after the number of locations).
- `MenuItem` gains a `divider` flag, `MenuSection` and `MenuItem` a `rawLabel` flag for labels that are not translation
  keys (still escaped).

#### Assets
- Stimulus controllers `form-window`, `form-window-dynamic-loader`, `list-window-refresh`, `window-close`,
  `window-drag`, `delete-item` and `clipboard`, as hand-written ES modules in `assets/dist/controllers/`.
- Every user-facing text and protocol name is a Stimulus value with an English default (error messages, clipboard
  texts and classes, CSRF header name); the URL of the component renderer is a value. The templates of `FormWindow`
  and `ListWindow` translate the messages of `form-window` and the loading error of the "New" button in the `east_ux`
  domain.
- `delete-item` removes the listeners of a confirmation when the modal is hidden: a cancelled confirmation no longer
  deletes its row on the next confirmation of another row.
- `delete-item` sends its CSRF token in the form field `csrf-field` (`_token` by default), read by East Common's
  `CheckCsrfToken` on a route declaring `csrfTokenId`, besides the header `csrf-header`.
- Structural stylesheet `assets/dist/styles/east-ux.css`, with a minimal neutral rendering of the windows.
- `assets/package.json` (`@teknoo/east-ux`) read by Symfony Flex and StimulusBundle, and valid for npm. The bundle maps
  `assets/dist` in AssetMapper as `@teknoo/east-ux`.

#### Configuration
- `teknoo_east_ux`: `home_route`, `form_theme`, `component_rendering.allow_unreferenced_components` (`false`).

#### Tests and documentation
- PHPUnit tests of the menu, the components, the renderer, the bundle and its configuration, and a kernel test booting
  a test application (East Foundation, East Common, Symfony UX, AssetMapper, SecurityBundle) that renders each
  component and checks the assets listed by `debug:asset-map`.
- Behat features: components rendered and re-rendered through East Common's recipes, form windows loaded through the
  component renderer, menu built from generators.
- Documentation of the components, the Live Components and the East bridge, the Stimulus controllers, the menu and
  the theming.
