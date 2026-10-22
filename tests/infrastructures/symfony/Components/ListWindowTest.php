<?php

/*
 * East UX.
 *
 * LICENSE
 *
 * This source file is subject to the 3-Clause BSD license
 * it is available in LICENSE file at the root of this package
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to richard@teknoo.software so we can send you a copy immediately.
 *
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 *
 * @link        https://teknoo.software/east-collection/ux Project website
 *
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */

declare(strict_types=1);

namespace Teknoo\Tests\East\UXBundle\Components;

use ArrayIterator;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Twig\ComponentLexer;
use Teknoo\East\UXBundle\Components\AbstractWindow;
use Teknoo\East\UXBundle\Components\ListWindow;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

use function array_column;
use function dirname;
use function file_get_contents;
use function get_object_vars;
use function in_array;
use function preg_match_all;
use function property_exists;
use function strtr;

/**
 * Live Twig component rendering the list window interface.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ListWindow::class)]
class ListWindowTest extends TestCase
{
    /**
     * View parameters set by East Common's rendering steps (`RenderList`, `RenderForm`, `RenderError`, `Render`) and
     * the form of the workplan: `LiveComponentBuilder` assigns each of them, by direct assignment, to the component
     * property of the same name.
     */
    private const array EAST_VIEW_PARAMETERS = [
        'objectsCollection',
        'page',
        'pageCount',
        'queryParams',
        'searchForm',
        'objectInstance',
        'request',
        'formView',
        'isTranslatable',
        'objectSaved',
        'error',
        'form',
        'objectViewKey',
    ];

    /**
     * Private properties of Symfony UX's `ComponentWithFormTrait`, used by ListWindow for its search form. PHP forbids
     * to redeclare them with another visibility. East Common's `RenderList`, the step rendering lists, never passes
     * them; a recipe passing `form` or `formView` to a page holding a ListWindow would break its live re-render.
     */
    private const array COMPONENT_WITH_FORM_TRAIT_PRIVATE_PROPERTIES = [
        'form',
        'formView',
    ];

    private RequestStack $requestStack;

    private FormFactoryInterface $formFactory;

    /**
     * @var list<array{0: string, 1: array<string, mixed>}>
     */
    private array $renderedComponents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestStack = $this->createStub(RequestStack::class);
        $this->formFactory = $this->createStub(FormFactoryInterface::class);
        $this->renderedComponents = [];
    }

    private function buildListWindow(): ListWindow
    {
        return new ListWindow(
            $this->requestStack,
            $this->formFactory,
        );
    }

    private function buildRenderableListWindow(): ListWindow
    {
        return new ListWindow(
            new RequestStack(),
            Forms::createFormFactory(),
        );
    }

    private function buildTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            /**
             * @param array<string, mixed> $parameters
             */
            public function trans(
                string $id,
                array $parameters = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                return '[' . ($domain ?? 'messages') . ']' . strtr($id, $parameters);
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }

    private function render(ListWindow $window): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');
        $loader->addPath(
            dirname((string) (new ReflectionClass(FormExtension::class))->getFileName(), 2) . '/Resources/views/Form',
        );

        $twig = new Environment($loader, ['strict_variables' => true, 'cache' => false]);
        $twig->setLexer(new ComponentLexer($twig));
        $twig->addExtension(new TranslationExtension($this->buildTranslator()));
        $twig->addExtension(new FormExtension());
        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(
            new FactoryRuntimeLoader([FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine)]),
        );

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name): string => '/url/' . $name,
        );
        $twig->addExtension(new RoutingExtension($urlGenerator));

        // Nested components (`<twig:ObjectList>`, `<twig:Pagination>`, `<twig:ConfirmModal>`) are only recorded
        $twig->addFunction(
            new TwigFunction(
                'component',
                function (string $name, array $props = []): string {
                    /** @var array<string, mixed> $props */
                    $this->renderedComponents[] = [$name, $props];

                    return '<component-' . $name . '/>';
                },
                ['is_safe' => ['html']],
            ),
        );

        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render(
            '@TeknooEastUX/components/ListWindow.html.twig',
            [
                ...get_object_vars($window),
                // Exposed by ComponentWithFormTrait (`#[ExposeInTemplate(name: 'form', getter: 'getFormView')]`)
                'form' => $window->getFormView(),
                'this' => $window,
                'attributes' => new ComponentAttributes([], $escaper),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getRenderedComponentProps(string $name): array
    {
        foreach ($this->renderedComponents as [$componentName, $props]) {
            if ($componentName === $name) {
                return $props;
            }
        }

        $this->fail($name . ' has not been rendered');
    }

    public function testConstruct(): void
    {
        $window = $this->buildListWindow();

        $this->assertInstanceOf(ListWindow::class, $window);
        $this->assertInstanceOf(AbstractWindow::class, $window);
    }

    public function testIsALiveComponent(): void
    {
        $attributes = (new ReflectionClass(ListWindow::class))->getAttributes(AsLiveComponent::class);

        // No explicit name nor template: they come from the `twig_component.defaults` entry of the bundle
        $this->assertCount(1, $attributes);
        $this->assertEquals([], $attributes[0]->getArguments());
    }

    public function testDefaultProperties(): void
    {
        $window = $this->buildListWindow();

        $this->assertEquals([], $window->objectsCollection);
        $this->assertEquals(0, $window->pageCount);
        $this->assertEquals(1, $window->page);
        $this->assertEquals('', $window->itemComponent);
        $this->assertEquals([], $window->tableHeaders);
        $this->assertNull($window->searchFormName);
        $this->assertEquals('', $window->newFormName);
        $this->assertEquals('', $window->newFormRoute);
        $this->assertEquals('', $window->newButtonLabel);
        $this->assertEquals('', $window->newFormTitle);
    }

    public function testNewButtonLabelsAreLiveProps(): void
    {
        foreach (['newButtonLabel', 'newFormTitle'] as $name) {
            $property = new ReflectionProperty(ListWindow::class, $name);

            $this->assertTrue($property->isPublic());
            $this->assertCount(1, $property->getAttributes(LiveProp::class));
        }
    }

    public function testOriginalPathComesFromTheCurrentRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/objects/list'));

        $window = new ListWindow($requestStack, $this->formFactory);

        $this->assertEquals('/objects/list', $window->originalPath);
    }

    /**
     * Names imported by the `use` statements at the top of the file of the class (classes, functions and constants,
     * without their alias); the `use` of a trait, inside the class, is not an import.
     *
     * @param class-string $className
     * @return list<string>
     */
    private static function getImportedNames(string $className): array
    {
        $source = (string) file_get_contents((string) (new ReflectionClass($className))->getFileName());
        preg_match_all('#^use\\s+(?:function\\s+|const\\s+)?([^\\s;]+)(?:\\s+as\\s+[^;]+)?;$#m', $source, $matches);

        return $matches[1];
    }

    public function testUnusedImportsAreDropped(): void
    {
        $imports = self::getImportedNames(ListWindow::class);
        $this->assertContains('Symfony\\Component\\Form\\FormInterface', $imports);

        foreach ($imports as $import) {
            $this->assertNotSame('ksort', $import);

            foreach (
                ['SearchFormRepository', 'UrlMapping', 'PostMount', 'SearchType', 'SubmitType', 'Promise', 'Search']
                as $className
            ) {
                $this->assertStringEndsNotWith('\\' . $className, $import);
            }
        }
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $window = $this->buildListWindow();

        foreach (self::EAST_VIEW_PARAMETERS as $name) {
            if (in_array($name, self::COMPONENT_WITH_FORM_TRAIT_PRIVATE_PROPERTIES, true)) {
                continue;
            }

            $assignable = !property_exists($window, $name)
                || (($property = new ReflectionProperty($window, $name))->isPublic() && !$property->isReadOnly());

            $this->assertTrue($assignable, $name . ' must be a public and writable property or no property');
        }

        // The view parameters of East Common's `RenderList` that ListWindow receives
        foreach (['objectsCollection', 'page', 'pageCount'] as $name) {
            $this->assertTrue((new ReflectionProperty(ListWindow::class, $name))->isPublic());
        }

        // The only exceptions: the private properties of Symfony UX's trait, not declared by the library
        $traitProperties = array_column(
            (new ReflectionClass(ComponentWithFormTrait::class))->getProperties(),
            'name',
        );
        foreach (self::COMPONENT_WITH_FORM_TRAIT_PRIVATE_PROPERTIES as $name) {
            $this->assertContains($name, $traitProperties);
            $this->assertTrue((new ReflectionProperty(ListWindow::class, $name))->isPrivate());
        }
    }

    public function testGetCurrentRequest(): void
    {
        $request = $this->createStub(Request::class);
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $window = new ListWindow(
            $requestStack,
            $this->formFactory,
        );

        $this->assertSame($request, $window->getCurrentRequest());
    }

    public function testGetCurrentRequestReturnsNull(): void
    {
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);

        $window = new ListWindow(
            $requestStack,
            $this->formFactory,
        );

        $this->assertNull($window->getCurrentRequest());
    }

    public function testHasObjectsWithEmptyArray(): void
    {
        $window = $this->buildListWindow();
        $window->objectsCollection = [];

        $this->assertFalse($window->hasObjects());
    }

    public function testHasObjectsWithNonEmptyArray(): void
    {
        $window = $this->buildListWindow();
        $window->objectsCollection = [new stdClass(), new stdClass()];

        $this->assertTrue($window->hasObjects());
    }

    public function testHasObjectsWithEmptyIterator(): void
    {
        $window = $this->buildListWindow();
        $window->objectsCollection = new ArrayIterator([]);

        $this->assertFalse($window->hasObjects());
    }

    public function testHasObjectsWithNonEmptyIterator(): void
    {
        $window = $this->buildListWindow();
        $window->objectsCollection = new ArrayIterator([new stdClass()]);

        $this->assertTrue($window->hasObjects());
    }

    public function testHasPaginationWhenPageCountIsOne(): void
    {
        $window = $this->buildListWindow();
        $window->pageCount = 1;

        $this->assertFalse($window->hasPagination());
    }

    public function testHasPaginationWhenPageCountIsZero(): void
    {
        $window = $this->buildListWindow();
        $window->pageCount = 0;

        $this->assertFalse($window->hasPagination());
    }

    public function testHasPaginationWhenPageCountIsGreaterThanOne(): void
    {
        $window = $this->buildListWindow();
        $window->pageCount = 5;

        $this->assertTrue($window->hasPagination());
    }

    public function testHasSearchForm(): void
    {
        $window = $this->buildListWindow();
        $this->assertFalse($window->hasSearchForm());

        $window->searchFormName = '';
        $this->assertFalse($window->hasSearchForm());

        $window->searchFormName = SearchType::class;
        $this->assertTrue($window->hasSearchForm());
    }

    public function testInstantiateFormWithoutSearchFormNameReturnsAnEmptyForm(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $window = new ListWindow(
            $this->requestStack,
            $formFactory,
        );

        $form = $this->createStub(FormInterface::class);
        $formFactory->expects($this->once())
            ->method('create')
            ->with(FormType::class)
            ->willReturn($form);

        $this->assertNull($window->searchFormName);

        $method = (new ReflectionClass($window))->getMethod('instantiateForm');
        $this->assertSame($form, $method->invoke($window));
    }

    public function testInstantiateFormWithEmptySearchFormNameReturnsAnEmptyForm(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $window = new ListWindow(
            $this->requestStack,
            $formFactory,
        );
        $window->searchFormName = '';

        $form = $this->createStub(FormInterface::class);
        $formFactory->expects($this->once())
            ->method('create')
            ->with(FormType::class)
            ->willReturn($form);

        $method = (new ReflectionClass($window))->getMethod('instantiateForm');
        $this->assertSame($form, $method->invoke($window));
    }

    public function testInstantiateFormThrowsExceptionWhenSearchFormNameIsNotAClass(): void
    {
        $window = $this->buildListWindow();
        $window->searchFormName = 'NonExistentClass';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('searchFormName must be a valid form class');

        $method = (new ReflectionClass($window))->getMethod('instantiateForm');
        $method->invoke($window);
    }

    public function testInstantiateFormWithValidFormClass(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $window = new ListWindow(
            $this->requestStack,
            $formFactory,
        );

        $window->searchFormName = SearchType::class;

        $form = $this->createStub(FormInterface::class);
        $formFactory->expects($this->once())
            ->method('create')
            ->with(SearchType::class)
            ->willReturn($form);

        $method = (new ReflectionClass($window))->getMethod('instantiateForm');
        $this->assertSame($form, $method->invoke($window));
    }

    public function testMountWithoutSearchForm(): void
    {
        $window = $this->buildRenderableListWindow();

        // PostMount hook of ComponentWithFormTrait: it must not throw without searchFormName
        $this->assertEquals([], $window->initializeForm([]));
        $this->assertEquals([], $window->formValues);
    }

    public function testReRenderWithoutSearchForm(): void
    {
        $mounted = $this->buildRenderableListWindow();
        $mounted->initializeForm([]);

        // Live re-render: a new instance hydrated from the props, the form is submitted before rendering
        $window = $this->buildRenderableListWindow();
        $window->formValues = $mounted->formValues;
        $window->isValidated = true;

        // Throws an UnprocessableEntityHttpException when the submitted form is not valid
        $window->submitFormOnRender();

        $this->assertTrue($window->isValidated);
        $this->assertEquals([], $window->validatedFields);
        $this->assertEquals([], $window->formValues);
    }

    public function testRenderWithoutSearchForm(): void
    {
        $window = $this->buildRenderableListWindow();
        $window->title = 'Objects';

        $output = $this->render($window);

        $this->assertStringContainsString('<h1 class="title">Objects</h1>', $output);
        $this->assertStringContainsString('data-controller="list-window-refresh"', $output);
        $this->assertStringNotContainsString('<form', $output);
        $this->assertStringNotContainsString('live#action:prevent', $output);
    }

    public function testRenderWithSearchForm(): void
    {
        $window = $this->buildRenderableListWindow();
        $window->searchFormName = SearchType::class;

        $output = $this->render($window);

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('data-action="live#action:prevent"', $output);
        $this->assertStringContainsString('data-live-action-param="save"', $output);
        $this->assertStringContainsString('type="search"', $output);
    }

    public function testRenderWithoutNewFormRouteHasNoNewButton(): void
    {
        $window = $this->buildRenderableListWindow();
        $window->newFormName = 'App\\Form\\ObjectType';
        $window->newButtonLabel = 'app.object.new';

        $output = $this->render($window);

        $this->assertStringNotContainsString('form-window-dynamic-loader', $output);
        $this->assertStringNotContainsString('app.object.new', $output);
        $this->assertStringNotContainsString('teknoo.east.ux.list_window.action.new', $output);
    }

    public function testRenderNewButtonWithDefaultLabels(): void
    {
        $window = $this->buildRenderableListWindow();
        $window->newFormRoute = 'app_object_new';
        $window->newFormName = 'App\\Form\\ObjectType';
        $window->closeAriaLabel = 'Close it';

        $output = $this->render($window);

        $this->assertStringContainsString('data-controller="form-window-dynamic-loader"', $output);
        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-url-value="/url/_teknoo_east_ux_component_render"',
            $output,
        );
        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-window-id-value="new-object-form"',
            $output,
        );
        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-form-path-value="/url/app_object_new"',
            $output,
        );
        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-form-name-value="App\\Form\\ObjectType"',
            $output,
        );
        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-title-value="[east_ux]teknoo.east.ux.list_window.action.new"',
            $output,
        );
        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-close-aria-label-value="Close it"',
            $output,
        );
        // The message of the loader's `alert()` comes from the `east_ux` catalogue
        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-load-error-text-value='
            . '"[east_ux]teknoo.east.ux.list_window.text.load_error"',
            $output,
        );
        $this->assertStringContainsString('data-action="click->form-window-dynamic-loader#open"', $output);
        $this->assertMatchesRegularExpression(
            '#>\s*\[east_ux\]teknoo\.east\.ux\.list_window\.action\.new\s*</button>#',
            $output,
        );
    }

    public function testRenderNewButtonWithLabelsFromProps(): void
    {
        $window = $this->buildRenderableListWindow();
        $window->newFormRoute = 'app_object_new';
        $window->newButtonLabel = 'app.object.new';
        $window->newFormTitle = 'app.object.title.new';

        $output = $this->render($window);

        // Labels supplied by the application: default domain
        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-title-value="[messages]app.object.title.new"',
            $output,
        );
        $this->assertMatchesRegularExpression('#>\s*\[messages\]app\.object\.new\s*</button>#', $output);
        $this->assertStringNotContainsString('teknoo.east.ux.list_window.action.new', $output);
    }

    public function testRenderNewButtonTitleDefaultsToTheButtonLabel(): void
    {
        $window = $this->buildRenderableListWindow();
        $window->newFormRoute = 'app_object_new';
        $window->newButtonLabel = 'app.object.new';

        $output = $this->render($window);

        $this->assertStringContainsString(
            'data-form-window-dynamic-loader-title-value="[messages]app.object.new"',
            $output,
        );
    }

    public function testRenderObjectListAndPagination(): void
    {
        $objects = [new stdClass()];
        $window = $this->buildRenderableListWindow();
        $window->objectsCollection = $objects;
        $window->itemComponent = 'ObjectRow';
        $window->tableHeaders = ['app.object.name'];
        $window->pageCount = 3;
        $window->page = 2;

        $this->render($window);

        $this->assertSame(
            [
                'objectsCollection' => $objects,
                'itemComponent' => 'ObjectRow',
                'tableHeaders' => ['app.object.name'],
            ],
            $this->getRenderedComponentProps('ObjectList'),
        );
        $this->assertSame(
            [
                'pageCount' => 3,
                'page' => 2,
            ],
            $this->getRenderedComponentProps('Pagination'),
        );
    }

    public function testRenderWithoutPagination(): void
    {
        $window = $this->buildRenderableListWindow();
        $window->pageCount = 1;

        $this->render($window);

        $this->assertNotContains('Pagination', array_column($this->renderedComponents, 0));
    }

    public function testRenderDeleteConfirmModal(): void
    {
        $window = $this->buildRenderableListWindow();

        $this->render($window);

        $this->assertSame(
            [
                'modalId' => 'delete-confirm-modal',
                'title' => '[east_ux]teknoo.east.ux.list_window.modal.delete_title',
                'message' => '[east_ux]teknoo.east.ux.list_window.modal.delete_message',
                'confirmText' => '[east_ux]teknoo.east.ux.list_window.action.delete',
                'cancelText' => '[east_ux]teknoo.east.ux.list_window.action.cancel',
                'confirmClass' => 'btn-danger',
            ],
            $this->getRenderedComponentProps('ConfirmModal'),
        );
    }
}
