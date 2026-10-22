# AGENTS.md - Project Knowledge Base

## Project Overview

`teknoo/east-ux` is a Symfony bundle built following the **#East programming philosophy**, on
`teknoo/east-foundation` and `teknoo/east-common`. It provides the application-level UI pieces of an East
application:

- Symfony UX Twig and Live Components (`Window`, `FormWindow`, `ListWindow`, `LoginWindow`, `MenuBar`, `ObjectList`,
  `Pagination`, `ConfirmModal`, `Card`) and their templates
- Seven Stimulus controllers and a structural stylesheet, served by AssetMapper (or consumed with npm)
- A component renderer endpoint (`POST /render`) rendering the component and the props of its JSON body, used to load a
  `FormWindow` dynamically; the props are validated by the validator of the component (tag
  `teknoo.east.ux.component_rendering.validation`), a component without validator is refused by default
- A menu service fed by the menu generators of the application

**Boundary rule**: East UX holds only these application-level pieces. The router logic and `LiveTrait` stay in East
Foundation, the `LiveComponentBuilder` and the recipe steps in East Common: East UX depends on them and never
re-implements them.

## Technical Stack

- **Language**: PHP 8.4+
- **Framework**: Symfony 7.4+ or 8.1+ (FrameworkBundle, Form, Security HTTP; SecurityBundle in the application)
- **UI**: Symfony UX TwigComponent 3 and LiveComponent 3, Twig 3, Bootstrap 5 classes
- **Front-end**: Stimulus controllers as hand-written ES modules (no build step), AssetMapper and StimulusBundle
  (optional), `assets/package.json` read by Symfony Flex and valid for npm
- **Dependency Injection**: Symfony Dependency Injection (`services.yaml`); PHP-DI files (`di.php`, `twig.php`) are
  empty today
- **Core Principles**: universal code (`src/`) separated from the Symfony implementation (`infrastructures/symfony/`);
  components never load objects, East recipes do and hand them over through the East bridge

## Project Structure

- `src/`: universal code, namespace `Teknoo\East\UX\` (`Contracts/Menu/`, `Menu/`: `Location`, `Sorting`,
  `MenuSection`, `MenuItem`, `MenuService`).
- `infrastructures/symfony/`: the bundle, namespace `Teknoo\East\UXBundle\`.
    - `TeknooEastUXBundle.php`, `DependencyInjection/` (configuration `teknoo_east_ux`, prepends of `twig_component`
      and AssetMapper).
    - `Components/`: the component classes.
    - `Controller/`: the component renderer.
    - `ComponentRendering/`, `Contracts/ComponentRendering/`: the validation of the props of the rendered components
      (`PropsValidatorInterface`, `PropsValidatorRegistry`, `FormWindowPropsValidator`, the exceptions).
    - `config/`: `services.yaml`, `routes.yaml`, `di.php`, `twig.php`.
    - `templates/`: Twig namespace `@TeknooEastUX` (`components/<Name>.html.twig`, `component_renderer.html.twig`).
    - `translations/`: catalogue of the `east_ux` domain.
- `assets/`: npm and Symfony UX package `@teknoo/east-ux` (`package.json`, `dist/controllers/*.js`,
  `dist/styles/east-ux.css`), mapped in AssetMapper as `@teknoo/east-ux`.
- `documentation/`: components, Live Components and the East bridge, Stimulus, menu, theming.
- `features/`: Behat features (components rendered by East recipes, dynamic form windows, menu).
- `tests/`: test suites:
    - `universal/`: menu tests (`Teknoo\Tests\East\UX\`).
    - `infrastructures/symfony/`: components, controller, bundle, DI and kernel tests (`Teknoo\Tests\East\UXBundle\`).
    - `behat/`: Behat `FeatureContext` (`Teknoo\Tests\East\UX\Behat\`).
    - `support/Fixtures/`: the test application shared by the kernel test and Behat (`Kernel`, configuration,
      templates, in-memory items, menu generators).
    - `var/`: cache and logs of the test application (ignored by git).

## Testing and Validation

All verification must be performed via the provided `Makefile`.

| Command           | Action                                                                      |
|-------------------|-----------------------------------------------------------------------------|
| `make depend`     | Install/update dependencies using Composer (`DEPENDENCIES=lowest` for the lowest versions). |
| `make test`       | Run unit tests (PHPUnit, with coverage) and behavior tests (Behat).          |
| `make qa`         | Run full quality assurance suite (Linting, PHPStan, PHPCS, Composer Audit). |
| `make qa-offline` | Run QA suite excluding security audit.                                      |
| `make clean`      | Remove `vendor/` and test cache directories.                                |

### Validation Details

- **Static Analysis**: PHPStan at level `max` on `src/` and `infrastructures/`.
- **Code Style**: PHPCS is used (PSR-12 standard).
- **Unit Testing**: PHPUnit; deprecations, notices, warnings and risky tests fail the suite.
- **Behavioral Testing**: Behat, on the test application of `tests/support/Fixtures/` (East Foundation, East Common,
  Symfony UX, AssetMapper, SecurityBundle and East UX), driven with HTTP requests.

## Development Conventions

- **Namespaces**:
    - Core: `Teknoo\East\UX\`
    - Infrastructure: `Teknoo\East\UXBundle\` (Symfony)
    - Tests: `Teknoo\Tests\East\UX\`, `Teknoo\Tests\East\UXBundle\`, `Teknoo\Tests\East\UX\Behat\`,
      `Teknoo\Tests\East\UX\Support\Fixtures\`
- **Coding Standard**: PSR-12, `declare(strict_types=1)` in every file, the East UX file header.
- **PHP Version**: Minimum requirement is PHP 8.4.
- **Components**: no non-public property named like a view parameter of the East recipes (`objectInstance`,
  `objectsCollection`, `pageCount`, `formView`, ...), except the private `form` and `formView` that `ListWindow`
  inherits from Symfony UX's `ComponentWithFormTrait` (a `ListWindow` can therefore not be placed in a page rendered
  by `RenderForm`, see [documentation/live-components.md](documentation/live-components.md#limitations)); LiveProps
  only on serializable values.
- **Translations**: keys `teknoo.east.ux.<component>.<group>.<id>` in the `east_ux` domain, always passed explicitly to
  the `trans` filter; labels supplied by the application keep its default domain.
- **Stimulus**: user-facing texts and protocol names are values with an English default; identifiers are the short
  names declared in `assets/package.json`.
