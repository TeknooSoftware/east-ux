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
   `pageCount`, `page`, `objectInstance`, `formView`, ...), and sets as controller the Live action of the request, when
   the conditions of [Lifecycle](#lifecycle) are met, or else the default action (`__invoke()`).
   Symfony UX then renders the component as for any live request. `SearchFormLoader` and `FormHandling` replay a
   submitted form from `_live_body`.

Consequences:

- Objects, collections and `FormView`s are never LiveProps (they can not be dehydrated): they are public properties
  filled by the recipe at each render. Only serializable values (strings, numbers, booleans, arrays of scalars) are
  LiveProps. `windowReference` is the only exception: a `WindowReference` has its own hydration extension.
- A property named like a view parameter of a recipe must be public and writable (see
  [components.md](components.md#convention-public-properties-for-the-view-parameters-of-the-recipes)).
- The access rules of the route of `originalPath` apply to the re-render: East Foundation checks them before running
  its recipe (see [Security](#security)).

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
- **Live render** (`component.render()` from JavaScript, `data-model` with `live#update`): the service is built again,
  the LiveProps are hydrated, the view parameters of the recipe are copied, then `__invoke()`.
  `FormWindow::__invoke()` calls `postMount()`; `ListWindow` uses `DefaultActionTrait` (an empty `__invoke()`).
- **Live action** (`component.action()`): the same render, with the method instead of `__invoke()`, so the method runs
  after the recipe and sees its view parameters. It needs `#[LiveAction]`, the header `X-Requested-With` and a recipe
  without error. Any other action only renders the component again, like the `save` of the search form of `ListWindow`.
- `#[PostMount]` runs once per instance and not on a re-render: never keep derived data in a property filled there;
  compute it from the inputs (`hasObjects()`, `hasSubForms()`) or in `__invoke()`.

## Dynamic windows

A Live Component inserted by JavaScript needs its `data-live-props-value` with the server's checksum, which JavaScript
can not compute. A window opened in the current page is therefore rendered by the server, through the component
renderer, and JavaScript appends the returned HTML to `<div id="dynamic-modals">`. No recipe runs in the renderer: the
window is rendered hidden and empty. Its live re-render, when it is opened, runs the recipe of its path, which loads
the objects and renders them through the East bridge.

### Form windows

The "New" button of `ListWindow` and the "Edit" buttons of the rows open a `FormWindow`: the
`form-window-dynamic-loader` controller ([stimulus.md](stimulus.md)) sends

```http
POST /component/render
Content-Type: application/json
X-Requested-With: XMLHttpRequest

{"component": "FormWindow", "props": {"windowId": "item-42", "formPath": "/items/edit/42",
 "originalPath": "/items/edit/42", "formName": "App\\Form\\Type\\ItemType", "title": "Item 42", "closable": true,
 "draggable": true, "closeAriaLabel": "Close", "hidden": true, "objectId": "42"}}
```

The window is rendered hidden and without form. The `form-window` controller then opens it: it sets `hidden` to false
and calls `component.render()`,
a live re-render: East Foundation re-routes it to the recipe of `formPath` (the creation or edition recipe), which
loads the object, builds the form and renders it through the East bridge.

### List windows

A `ListWindow` loaded by the renderer floats over the current page (`floating`), so several lists are open together.
A link with the `window-dynamic-loader` controller ([stimulus.md](stimulus.md#window-dynamic-loader)) sends its body.
This body names the path of its list recipe in `originalPath`, the path of the link: in the renderer, the current
request is `POST /component/render`, the path that `LiveTrait` would keep otherwise.

```http
POST /component/render
Content-Type: application/json
X-Requested-With: XMLHttpRequest

{"component": "ListWindow", "props": {"windowId": "items", "originalPath": "/items/", "itemComponent": "ItemRow",
 "floating": true, "hidden": true, "title": "Items", "closable": true, "draggable": true,
 "tableHeaders": ["app.item.table.name", "app.item.table.actions"],
 "searchFormName": "App\\Form\\Type\\ItemSearchType", "newFormName": "App\\Form\\Type\\ItemType",
 "newFormRoute": "app_item_new"}}
```

The window is rendered hidden and without list. The `list-window-refresh` controller opens it on the `window:open`
dispatched by the loader: it sets `hidden` to false and re-renders the window, and East Foundation re-routes this
re-render to the recipe of `originalPath` (the list recipe), which loads the page of objects. The page of a floating
window is not bound to the URL: its pagination re-renders it through the same recipe without changing the URL of the
page holding it ([components.md](components.md#listwindow)).

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
- The library references two components, with validators built on Symfony's validator (`Assert\Collection`, no
  extra field):
    - `FormWindow`, with `FormWindowPropsValidator`: `windowId`, `formPath` and `formName` (a form type) are
      required, `originalPath`, `objectId`, `title`, `closeAriaLabel` (strings) and `closable`, `draggable`, `hidden`
      (booleans) are optional, any other prop (`objectInstance`, `formTheme`, `isLive`, ...) is refused.
    - `ListWindow`, with `ListWindowPropsValidator`: `windowId` and `originalPath` (non-empty strings),
      `itemComponent` (a component known by Symfony UX, anonymous components included) and `floating` (`true`: a list
      window loaded by the renderer always floats) are required. The window props `title`, `closeAriaLabel`,
      `resizeAriaLabel` (strings) and `closable`, `resizable`, `draggable`, `hidden` (booleans), `tableHeaders` (an
      array of strings), `searchFormName` and `newFormName` (form types), `newFormRoute` (a route generated without
      parameters, as the template calls `path(newFormRoute)`), `newButtonLabel`, `newFormTitle` (strings) and `page`
      (a positive integer) are optional. Any other prop is refused: `closeRedirectUrl`, the view parameters of the
      recipes (`objectsCollection`, `pageCount`, `formView`), the LiveProps of the search form (`formValues`,
      `isValidated`, `validatedFields`), `@checksum`, ...
    - A prop is `null` only when its property is nullable (`originalPath`, `objectId` of `FormWindow`,
      `searchFormName` of `ListWindow`): any other `null` prop gets a 400, not a `TypeError` of the component.
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

## References of the windows

A Live window with a `WindowReference` runs the methods sent to it from outside of its page: by the worker of a task,
by another tab, by another browser. Instead of polling, the window waits for a message. Mercure only carries the name
of the method, never business data: the method runs in a Live request, behind the firewall, and the recipe of the window
reads the data. For a shipped window the query is this recipe, `refresh()` has nothing to do. A method of a custom
window can run its own, it runs after the view parameters and before the render.

`Window` and `LoginWindow` are not Live, their reference is refused. A `WindowReference` holds the class of the
component, its `id` (the Mercure topic) and the list of its callable methods, public, without parameter and returning
`void`. `AbstractWindow::createReference()` gives the methods `refresh` and `close`, a child class can override it.

### Creating the reference

The step `CreateWindowReferenceInterface` creates it before the rendering, from `windowClass`. It writes the reference
in the workplan under `workPlanKey` (`WindowReference::class` by default) and in the view under `viewKey`
(`windowReference` by default). It refuses a class which is not a Live window, and a method without `#[LiveAction]`.

A parameter left open can be set by the request, even with a default value: `?viewKey=foo` would break the window. So
`windowClass`, `viewKey` and `workPlanKey` come from a default of the route or from a `Value` of the plan. East Common's
`LoadObject` and `CreateObject` read `workPlanKey` too, a default of the route would also change where they store their
object: in a plan running them, this key must be a `Value`.

```php
// A plan of the application, the step is a public service of the bundle
$recipe = $recipe->cook(
    $this->createWindowReference,
    CreateWindowReferenceInterface::class,
    ['viewKey' => new Value('windowReference'), 'workPlanKey' => new Value(WindowReference::class)],
    10,
);
$recipe = $recipe->execute($this->listObjectEndPoint, ListObjectEndPointInterface::class, 1, 20);
```

```yaml
# The route, with the usual defaults of the list
app_item_followed:
    path: '/items/followed/'
    defaults:
        _controller: 'app.endpoint.item.followed'
        windowClass: 'Teknoo\East\UXBundle\Components\ListWindow'
```

```twig
<twig:ListWindow windowReference="{{ windowReference }}" objectsCollection="{{ objectsCollection }}" ... />
```

Each window of a page needs its own reference: cook the step twice, with another `viewKey`, and another `workPlanKey`
when a next step reads the reference: the second run replaces the first one otherwise. Without step,
`<twig:ListWindow windowReference="{{ reference }}" />` takes a reference created by the application.

With a reference, `WindowReferenceAttributesSubscriber` adds the Stimulus controller `window-reference` to the root
element of the window, and grants the topics of the windows in the Mercure cookie of the response
([stimulus.md](stimulus.md#window-reference)). Add `window-reference` to `assets/controllers.json`.

### Calling a method

`WindowMethodPublisherInterface::publish()` sends a method of the reference. It throws for a method out of the
reference, without hub, and when the hub fails.

```php
// A step of the plan of a task, in the worker
$reference = $task->getWindowReference();
if (null !== $reference) {
    $this->publisher->publish($reference, 'refresh');
}
```

A reference is a serializable value, a task carries it to the worker. For another tab or browser, which has its own
window and its own id, the application keeps the id and rebuilds the reference:
`new WindowReference(ListWindow::class, $id, ['refresh', 'close'])`.

To follow a task, a page needs a custom Live window: `Window` is not Live. Follow the checklist of
[a new Live Component](#checklist): without `LiveTrait` and `originalPath`, no recipe runs and the window has no data.

### Mercure

The updates are private and the topic is a constant IRI, `WindowMethodPublisher::TOPIC_PREFIX` followed by the id: the
hub never fetches it, and a worker needs no host to build it. The subscribers hold one grant for all the windows,
`WindowMethodPublisher::TOPIC_PATTERN`, of type `urlpattern`, because a Mercure cookie is replaced by the next response
setting it: with a grant for each topic, a window would lose its grant as soon as another one is rendered. An
authenticated user can therefore see window ids and method names, never business data.

**A page writing its own Mercure cookie must add this grant**, or the windows opened in the other tabs stop receiving
their messages at their next reconnection, without error:

```twig
{% set pattern = constant('Teknoo\\East\\UXBundle\\Mercure\\WindowMethodPublisher::TOPIC_PATTERN') %}
{{ mercure(topic, {'subscribe': {'exact': [topic], 'urlpattern': [pattern]}}) }}
```

The hub must speak the protocol 1.0 and be on the same origin as the application (scheme, host and port): `EventSource`
sends the cookie only there. A worker needs the remote URL of the hub: an empty URL selects the hub of FrankenPHP, which can not publish from the CLI.

### Limits

- A `refresh` of an open `FormWindow` erases what the user is typing (`data-skip-morph`). Do not send it to a form
  being edited.
- A message sent before the subscription is lost, there is no replay.
- A window without any re-render stops receiving messages when its token expires: the hub closes the connection and the
  reconnection is refused. Any re-render renews the cookie.
- `close` hides the window, `closeRedirectUrl` stays a behaviour of the browser: a full page window leaves an empty page.
- A `close` waiting with an action of the user in the same Live request is lost: both leave as one `_batch` request, which
  the East bridge does not run, the window is only rendered again. A `refresh` is not concerned.
- A window loaded by the component renderer gets its reference at its first opening, from the recipe of its path:
  nothing is subscribed before. The props sent to the renderer can not hold a reference.
- A recipe re-run by a re-render creates a new reference, ignored by the window: the reference of the workplan is only
  the reference of the window in the run displaying it. If the recipe of the window also starts a task, each `refresh`
  starts one: the two recipes must be different.

## Security

- `^/component` (the renderer) and `^/_components` (the Live Component endpoint) require an authenticated user in the
  application's `access_control`. Pages of anonymous users use no Live Component.
- For a re-render, East Foundation's router runs the recipe of the route named by the signed `originalPath`. The
  firewall's `access_control` only sees `/_components/...`, so East Foundation (9.4) asks the access listener of the
  firewall about the target route before running its recipe: a refused access answers 403 (or starts the
  authentication of an anonymous user). The checksum prevents a client from changing `originalPath`; a recipe that
  needs more than its URL rules (an owner check, ...) does it in its own steps, as East Common's access control steps
  do.
- A Live action runs after the recipe of the route of `originalPath`, never after an error of this recipe, and only with
  the header `X-Requested-With`: a request without it fails, the method does not run.
- A client can not choose a reference: `windowReference` is not writable, its value is covered by the checksum of the
  props, and the validators of the component renderer refuse it.
- The component renderer only validates the props: it does not check `originalPath` or `formPath` against the access
  rules of their routes. These rules apply to the re-render that opens the window: a user who may not open the route
  of `originalPath` gets a 403, and the window shows nothing of the recipe.
- A `ListWindow` with a `searchFormName` builds its search form in the renderer (Symfony UX's `ComponentWithFormTrait`)
  and renders it in the response, before any recipe or access rule of `originalPath`. So any authenticated user may
  render any form type of the application this way, with the choices it loads (from a database, ...). The renderer
  keeps no list of the allowed paths or form types: a form type must not render data that an authenticated user may
  not see.

## Morphing and Stimulus

- On a re-render, Live Components morph the DOM. On an element with `data-skip-morph` (the `<form>` of `FormWindow`),
  the element's content is replaced by the server's instead of being morphed, so the field values come from the
  freshly rendered form.
- Stimulus controllers are attached with `attributes.defaults({'data-controller': '...'})`, which merges them with the
  `live` controller that Symfony UX adds (`data-controller="form-window window-drag live"`). Never write a second
  `data-controller` attribute next to `{{ attributes }}`: the browser keeps only one.
- The `Pagination` buttons update the `page` prop of `ListWindow` (`data-model="page"`, `live#update`); the search
  form calls `live#action:prevent` with the action parameter `save`, the form being resubmitted on the re-render.

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
