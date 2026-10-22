# Live Components and the East bridge

`FormWindow` and `ListWindow` are Symfony UX Live Components: the server re-renders them through
`/_components/<Name>`. In an East application, a page is rendered by an East recipe, and the objects a component shows
are loaded by that recipe. East Foundation and East Common glue Live Components to the recipes, so a re-render runs
the recipe of the page again instead of letting the component load anything itself. East UX only uses this bridge:
the router logic and `LiveTrait` stay in East Foundation, the `LiveComponentBuilder` and the recipe steps in East
Common.

## How a re-render reaches the East recipe

1. **The page.** A route of an East recipe (for example `teknoo.east.common.endpoint.crud.list`) renders a template
   holding `<twig:ListWindow objectsCollection="{{ objectsCollection }}" pageCount="{{ pageCount }}" ... />`.
   East Foundation's `LiveTrait` (`Teknoo\East\FoundationBundle\UX\Components\LiveTrait`) adds the LiveProp
   `originalPath`: `ListWindow` fills it with the path of the current request, `FormWindow` with its `formPath`. Symfony
   UX signs the props (`@checksum`) in `data-live-props-value`.
2. **The re-render request.** `live_controller.js` sends `POST /_components/ListWindow` with a form field `data`
   holding `{"props": {...}, "updated": {...}}`, and the header `X-Requested-With: XMLHttpRequest`.
3. **East Foundation's router** matches the `ux_live_component` route, checks the checksum (HMAC-SHA256 on
   `kernel.secret`, the component name in the pre-image), matches `props.originalPath` against the routes and runs
   **that route's recipe**. The live payload is available as the request attributes `_live_parameters` and
   `_live_body`; the scalar props and the updated props (`page`, ...) become request attributes too, so the recipe
   reads the new page like a query parameter.
4. **East Common.** When the recipe renders (`Render`, `RenderForm`, `RenderList`, `RenderError`), `TemplateTrait`
   hands over to `LiveComponentBuilder`: it gets the component service, hydrates its LiveProps from the payload,
   **copies every view parameter of the recipe into the property of the same name** (`objectsCollection`,
   `pageCount`, `page`, `objectInstance`, `formView`, ...), and sets the default action (`__invoke()`) as controller.
   Symfony UX then renders the component as for any live request. `SearchFormLoader` and `FormHandling` replay a
   submitted form from `_live_body`.

Consequences:

- Objects, collections and `FormView`s are never LiveProps (they can not be dehydrated): they are public properties
  filled by the recipe at each render. Only serializable values (strings, numbers, booleans, arrays of scalars) are
  LiveProps.
- A property named like a view parameter of a recipe must be public and writable (see
  [components.md](components.md#convention-public-properties-for-the-view-parameters-of-the-recipes)).
- The access rules of the recipe's route are not applied to the re-render (see [Security](#security)).

## Application setup

```yaml
# config/routes/ux_live_component.yaml: the Live Component endpoint, re-routed by East Foundation
live_component:
    resource: '@LiveComponentBundle/config/routes.php'
    prefix: '/_components'

# config/routes/east_ux.yaml: the component renderer, on `POST /component/render`
east_ux:
    resource: '@TeknooEastUXBundle/config/routes.yaml'
    prefix: '/component'
```

```yaml
# config/packages/security.yaml
security:
    access_control:
        # ...
        - { path: '^/component', roles: [IS_AUTHENTICATED_FULLY], requires_channel: https }
        - { path: '^/_components', roles: [IS_AUTHENTICATED_FULLY], requires_channel: https }
```

East Foundation's router is enabled by `EastFoundationBundle`; East Common's bridge activates when the services of
Symfony UX exist (`TwigComponentBundle` and `LiveComponentBundle` registered).

## Lifecycle

- **Page render** (`<twig:FormWindow ... />`): the component service is built with its constructor dependencies, the
  attributes become props, `#[PostMount]` runs (`FormWindow::postMount()` builds the form view and copies `formPath`
  into `originalPath`), then the template.
- **Live render** (`component.render()` from JavaScript, a `LiveAction`, `data-model` with `live#update`): the service
  is built again, the LiveProps are hydrated, the view parameters of the recipe are copied, then `__invoke()`.
  `FormWindow::__invoke()` calls `postMount()`; `ListWindow` uses `DefaultActionTrait` (an empty `__invoke()`).
- `#[PostMount]` runs once per instance and not on a re-render: never keep derived data in a property filled there;
  compute it from the inputs (`hasObjects()`, `hasSubForms()`) or in `__invoke()`.

## Dynamic form windows

A Live Component inserted by JavaScript needs its `data-live-props-value` with the server's checksum, which JavaScript
can not compute. The "New" button of `ListWindow` and the "Edit" buttons of the rows therefore ask the server to render
the window: the `form-window-dynamic-loader` controller ([stimulus.md](stimulus.md)) sends

```http
POST /component/render
Content-Type: application/json
X-Requested-With: XMLHttpRequest

{"component": "FormWindow", "props": {"windowId": "item-42", "formPath": "/items/edit/42",
 "originalPath": "/items/edit/42", "formName": "App\\Form\\Type\\ItemType", "title": "Item 42", "closable": true,
 "draggable": true, "closeAriaLabel": "Close", "hidden": true, "objectId": "42"}}
```

and appends the returned HTML to `<div id="dynamic-modals">`. The window is rendered hidden and without form. The
`form-window` controller then opens it with `component.render()`, a live re-render: East Foundation re-routes it to the recipe of `formPath` (the creation or edition recipe), which loads
the object, builds the form and renders it through the East bridge.

### The component renderer

`Teknoo\East\UXBundle\Controller\ComponentRenderer`, route `_teknoo_east_ux_component_render` (`POST /render`,
imported with a prefix), renders `@TeknooEastUX/component_renderer.html.twig` (`{{ component(componentName, props) }}`)
with the `component` and the `props` of the JSON body. A body that is not a JSON object, or without `component` or
`props`, gets a 400 `Missing component or props`.

The props are validated before the rendering:

- A component is referenced by a validator of its props, a service implementing
  `Teknoo\East\UXBundle\Contracts\ComponentRendering\PropsValidatorInterface`, tagged
  `teknoo.east.ux.component_rendering.validation` with the class of its component in the attribute `component`. As
  any East contract, `validate(array $props, PromiseInterface $promise)` returns the validator itself: it calls
  `success($props)` of the promise, or `fail()` with an `InvalidPropsException`.
- `PropsValidatorRegistry` receives the tagged validators, indexed by this class, and its
  `validate(string $component, array $props, PromiseInterface $promise)` hands the props to the validator of the
  component. Without validator (an anonymous or an unknown component is looked up by its name), it ignores the props
  and calls `success()` when `teknoo_east_ux.component_rendering.allow_unreferenced_components` is `true`, or calls
  `fail()` with an `UnreferencedComponentException`.
- The controller throws the error passed to `fail()`: invalid props get a 400 (`InvalidPropsException`), a component
  without validator a 403 (`UnreferencedComponentException`); props that are not an object get a 400.
- The library references `FormWindow` with `FormWindowPropsValidator`: `windowId`, `formPath` and `formName` (a form
  type) are required, `originalPath`, `objectId`, `title`, `closeAriaLabel` (strings) and `closable`, `draggable`,
  `hidden` (booleans) are optional, any other prop (`objectInstance`, `formTheme`, `isLive`, ...) is refused.
- An application renders another component through the renderer by registering a validator for it:

  ```yaml
  # config/services.yaml
  App\ComponentRendering\CardPropsValidator:
      arguments: ['@validator']
      tags:
          - { name: 'teknoo.east.ux.component_rendering.validation', component: 'Teknoo\East\UXBundle\Components\Card' }
  ```

- The access rules of the application decide who may call it (see [Security](#security)).
- The controller extends Symfony's `AbstractController`: East Foundation's router hands a route back to Symfony only
  for such a controller (any other invokable controller would be run as an East endpoint).

## Security

- `^/component` (the renderer) and `^/_components` (the Live Component endpoint) require an authenticated user in the
  application's `access_control`. Pages of anonymous users use no Live Component.
- For a re-render, East Foundation's router runs the recipe of the route named by the signed `originalPath`. The
  firewall's `access_control` only sees `/_components/...`, so East Foundation (9.4) asks the access listener of the
  firewall about the target route before running its recipe: a refused access answers 403 (or starts the
  authentication of an anonymous user). The checksum prevents a client from changing `originalPath`; a recipe that
  needs more than its URL rules (an owner check, ...) does it in its own steps, as East Common's access control steps
  do.

## Morphing and Stimulus

- On a re-render, Live Components morph the DOM. On an element with `data-skip-morph` (the `<form>` of `FormWindow`),
  the element's content is replaced by the server's instead of being morphed, so the field values come from the
  freshly rendered form.
- Stimulus controllers are attached with `attributes.defaults({'data-controller': '...'})`, which merges them with the
  `live` controller that Symfony UX adds (`data-controller="form-window window-drag live"`). Never write a second
  `data-controller` attribute next to `{{ attributes }}`: the browser keeps only one.
- The `Pagination` buttons update the `page` prop of `ListWindow` (`data-model="page"`, `live#update`); the search
  form calls `live#action:prevent` with the action parameter `save`, the form being resubmitted on the re-render.

## Limitations

- **Window props of `ListWindow`.** They are not LiveProps: a re-render shows the window with their default values
  (an empty title, the default ARIA labels). `FormWindow` redeclares them as LiveProps and keeps them.

## Anti-patterns

| Anti-pattern                                             | Problem                                                                  |
|----------------------------------------------------------|--------------------------------------------------------------------------|
| `#[LiveProp] public ?FormView $formView`                 | A `FormView` can not be dehydrated                                       |
| A private or read-only property named like a view parameter | `LiveComponentBuilder` ignores it: the view parameter never reaches the component |
| Computing a value once in `#[PostMount]`                 | Not recomputed on a re-render unless `__invoke()` calls it               |
| Building a live component's HTML in JavaScript           | The checksum can not be computed client side: use the component renderer |
| `data-controller="x" {{ attributes }}`                   | Duplicate attribute, not merged: use `attributes.defaults()`             |
| `path(this.formPath)`                                    | `formPath` is already a path (`newFormRoute` is a route name)            |
| Loading objects inside a component                       | Load them in the East recipe, behind its access control; the component only receives them |

## Checklist

**A new Live Component tied to a recipe**

- [ ] `#[AsLiveComponent]` only when the component must re-render on the server, `#[AsTwigComponent]` otherwise.
- [ ] `LiveTrait` for `originalPath`, and `DefaultActionTrait` or an `__invoke()`.
- [ ] LiveProps only on serializable values; objects and forms in public properties named like the view parameters.
- [ ] Derived data in getters or in `__invoke()`, not cached from `#[PostMount]`.
- [ ] `data-skip-morph` on a `<form>` whose values must refresh; controllers merged with `attributes.defaults()`.

**Debugging a re-render**

- [ ] `POST /_components/<Name>` answers 200 and `props.originalPath` names the route of the right recipe?
- [ ] An object is missing after a re-render: is it a view parameter of the recipe with the name of a public property?
- [ ] 401 or 403: is the user authenticated for `^/_components`?
- [ ] A form does not refresh: `data-skip-morph` on the `<form>`?
