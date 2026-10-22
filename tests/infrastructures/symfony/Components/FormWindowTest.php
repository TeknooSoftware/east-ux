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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Twig\ComponentLexer;
use Teknoo\East\UXBundle\Components\AbstractWindow;
use Teknoo\East\UXBundle\Components\FormWindow;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function dirname;
use function file_get_contents;
use function get_object_vars;
use function preg_match_all;
use function property_exists;
use function strtr;
use function substr_count;

/**
 * Live Twig component rendering the form window interface.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(FormWindow::class)]
class FormWindowTest extends TestCase
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

    private const string TEST_THEME = 'test_theme.html.twig';

    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formFactory = $this->createStub(FormFactoryInterface::class);
    }

    private function buildFormWindow(?string $formTheme = null): FormWindow
    {
        return new FormWindow($this->formFactory, $formTheme);
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

    /**
     * @param array<string, string> $attributes
     */
    private function render(FormWindow $window, array $attributes = []): string
    {
        $templatesLoader = new FilesystemLoader();
        $templatesLoader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');
        $templatesLoader->addPath(
            dirname((string) (new ReflectionClass(FormExtension::class))->getFileName(), 2) . '/Resources/views/Form',
        );

        $themeLoader = new ArrayLoader([
            self::TEST_THEME => '{% block form_widget_simple %}<input class="test-theme-widget">{% endblock %}',
        ]);

        $twig = new Environment(
            new ChainLoader([$themeLoader, $templatesLoader]),
            ['strict_variables' => true, 'cache' => false],
        );
        $twig->setLexer(new ComponentLexer($twig));
        $twig->addExtension(new TranslationExtension($this->buildTranslator()));
        $twig->addExtension(new FormExtension());
        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(
            new FactoryRuntimeLoader([FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine)]),
        );

        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render(
            '@TeknooEastUX/components/FormWindow.html.twig',
            [
                ...get_object_vars($window),
                'this' => $window,
                'attributes' => new ComponentAttributes($attributes, $escaper),
            ],
        );
    }

    private function buildFormView(bool $withSubForm): FormView
    {
        $formFactory = Forms::createFormFactory();
        $builder = $formFactory->createNamedBuilder('object', FormType::class)
            ->add('name', TextType::class, ['label' => 'app.object.name']);

        if ($withSubForm) {
            $builder->add(
                $formFactory->createNamedBuilder('address', FormType::class, null, ['label' => 'app.object.address'])
                    ->add('street', TextType::class),
            );
        }

        return $builder->getForm()->createView();
    }

    public function testConstruct(): void
    {
        $window = $this->buildFormWindow();

        $this->assertInstanceOf(FormWindow::class, $window);
        $this->assertInstanceOf(AbstractWindow::class, $window);
    }

    public function testIsALiveComponent(): void
    {
        $attributes = (new ReflectionClass(FormWindow::class))->getAttributes(AsLiveComponent::class);

        // No explicit name nor template: they come from the `twig_component.defaults` entry of the bundle
        $this->assertCount(1, $attributes);
        $this->assertEquals([], $attributes[0]->getArguments());
    }

    public function testDefaultProperties(): void
    {
        $window = $this->buildFormWindow();

        $this->assertEquals('', $window->title);
        $this->assertFalse($window->closable);
        $this->assertFalse($window->resizable);
        $this->assertFalse($window->draggable);
        $this->assertEquals('', $window->closeRedirectUrl);
        $this->assertEquals('Close', $window->closeAriaLabel);
        $this->assertEquals('Resize', $window->resizeAriaLabel);
        $this->assertEquals('', $window->windowId);
        $this->assertNull($window->objectId);
        $this->assertNull($window->formView);
        $this->assertNull($window->objectInstance);
        $this->assertNull($window->formName);
        $this->assertEquals('', $window->formPath);
        $this->assertTrue($window->hidden);
        $this->assertTrue($window->isLive);
        $this->assertNull($window->formTheme);
    }

    public function testFormThemeDefaultsToTheBundleConfiguration(): void
    {
        $this->assertNull($this->buildFormWindow(null)->formTheme);
        $this->assertEquals(
            '@App/form/fields.html.twig',
            $this->buildFormWindow('@App/form/fields.html.twig')->formTheme,
        );
    }

    public function testFormThemeIsALivePropKeptAcrossReRenders(): void
    {
        $property = new ReflectionProperty(FormWindow::class, 'formTheme');

        $this->assertTrue($property->isPublic());
        $this->assertCount(1, $property->getAttributes(LiveProp::class));
        // Not writable: the browser cannot change the theme of a rendered window
        $this->assertEquals([], $property->getAttributes(LiveProp::class)[0]->getArguments());
    }

    public function testFormThemeCanBeOverriddenByAProp(): void
    {
        $window = $this->buildFormWindow('@App/form/fields.html.twig');
        $window->formTheme = '@App/form/other.html.twig';

        $this->assertEquals('@App/form/other.html.twig', $window->formTheme);
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
        $imports = self::getImportedNames(FormWindow::class);
        $this->assertContains('Symfony\\Component\\Form\\FormView', $imports);
        $this->assertNotContains('Symfony\\Component\\HttpFoundation\\RequestStack', $imports);

        foreach ($imports as $import) {
            $this->assertStringEndsNotWith('\\Search', $import);
            $this->assertStringEndsNotWith('\\DefaultActionTrait', $import);
        }
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $window = $this->buildFormWindow();

        foreach (self::EAST_VIEW_PARAMETERS as $name) {
            $assignable = !property_exists($window, $name)
                || (($property = new ReflectionProperty($window, $name))->isPublic() && !$property->isReadOnly());

            $this->assertTrue($assignable, $name . ' must be a public and writable property or no property');
        }

        // The view parameters of East Common's `RenderForm` that FormWindow receives
        $this->assertTrue((new ReflectionProperty(FormWindow::class, 'formView'))->isPublic());
        $this->assertTrue((new ReflectionProperty(FormWindow::class, 'objectInstance'))->isPublic());
    }

    public function testCloseAction(): void
    {
        $window = $this->buildFormWindow();
        $window->hidden = false;

        $window->close();

        $this->assertTrue($window->hidden);
    }

    public function testHasSubForms(): void
    {
        $window = $this->buildFormWindow();
        $this->assertFalse($window->hasSubForms());

        $window->formView = new FormView();
        $window->formView->children['name'] = new FormView($window->formView);
        $this->assertFalse($window->hasSubForms());

        $subForm = new FormView($window->formView);
        $subForm->vars['compound'] = true;
        $window->formView->children['address'] = $subForm;
        $this->assertTrue($window->hasSubForms());
    }

    public function testPostMountWithFormViewAlreadySet(): void
    {
        $window = $this->buildFormWindow();
        $formView = $this->createStub(FormView::class);
        $window->formView = $formView;

        $window->postMount();

        $this->assertSame($formView, $window->formView);
    }

    public function testPostMountWithObjectInstance(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $window = new FormWindow($formFactory, null);

        $objectInstance = new stdClass();
        $window->objectInstance = $objectInstance;
        $window->formName = FormType::class;

        $form = $this->createStub(FormInterface::class);
        $formView = $this->createStub(FormView::class);
        $form->method('createView')->willReturn($formView);

        $formFactory->expects($this->once())
            ->method('create')
            ->with(FormType::class, $objectInstance)
            ->willReturn($form);

        $window->postMount();

        $this->assertSame($formView, $window->formView);
    }

    public function testPostMountWithNonObjectInstance(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $window = new FormWindow($formFactory, null);

        $window->objectInstance = 'new';
        $window->formName = FormType::class;

        $form = $this->createStub(FormInterface::class);
        $formView = $this->createStub(FormView::class);
        $form->method('createView')->willReturn($formView);

        $formFactory->expects($this->once())
            ->method('create')
            ->with(FormType::class, null)
            ->willReturn($form);

        $window->postMount();

        $this->assertSame($formView, $window->formView);
    }

    public function testPostMountWithoutObjectInstance(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $window = new FormWindow($formFactory, null);
        $window->formName = FormType::class;

        $formFactory->expects($this->never())->method('create');

        $window->postMount();

        $this->assertNull($window->formView);
    }

    public function testPostMountWithFormPath(): void
    {
        $window = $this->buildFormWindow();
        $window->formPath = '/test/path';

        $window->postMount();

        $this->assertEquals('/test/path', $window->originalPath);
    }

    public function testInvoke(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $window = new FormWindow($formFactory, null);

        $objectInstance = new stdClass();
        $window->objectInstance = $objectInstance;
        $window->formName = FormType::class;

        $form = $this->createStub(FormInterface::class);
        $formView = $this->createStub(FormView::class);
        $form->method('createView')->willReturn($formView);

        $formFactory->expects($this->once())
            ->method('create')
            ->with(FormType::class, $objectInstance)
            ->willReturn($form);

        $window();

        $this->assertSame($formView, $window->formView);
    }

    public function testRenderWithoutFormView(): void
    {
        $window = $this->buildFormWindow();
        $window->windowId = 'empty-window';

        $output = $this->render($window);

        $this->assertStringContainsString('<div class="window form-window d-none"', $output);
        $this->assertStringContainsString('data-controller="form-window"', $output);
        $this->assertStringNotContainsString('<form', $output);
    }

    public function testRenderLiveGivesTheTranslatedMessagesToTheFormWindowController(): void
    {
        $window = $this->buildFormWindow();

        $output = $this->render($window);

        $this->assertStringContainsString(
            'data-form-window-error-text-value="[east_ux]teknoo.east.ux.form_window.text.error"',
            $output,
        );
        $this->assertStringContainsString(
            'data-form-window-network-error-text-value="[east_ux]teknoo.east.ux.form_window.text.network_error"',
            $output,
        );
    }

    public function testRenderLiveKeepsTheMessagesGivenByTheApplication(): void
    {
        $window = $this->buildFormWindow();

        $output = $this->render($window, ['data-form-window-error-text-value' => 'Saving failed']);

        $this->assertStringContainsString('data-form-window-error-text-value="Saving failed"', $output);
        $this->assertStringNotContainsString('teknoo.east.ux.form_window.text.error', $output);
        $this->assertStringContainsString(
            'data-form-window-network-error-text-value="[east_ux]teknoo.east.ux.form_window.text.network_error"',
            $output,
        );
    }

    public function testRenderNotLive(): void
    {
        $window = $this->buildFormWindow();
        $window->isLive = false;
        $window->hidden = false;
        $window->closable = true;
        $window->draggable = true;
        $window->closeRedirectUrl = '/list';

        $output = $this->render($window);

        $this->assertStringContainsString('<div class="window draggable form-window form-fixed"', $output);
        $this->assertStringContainsString('data-controller="window-close window-drag"', $output);
        $this->assertStringContainsString('data-window-close-redirect-url-value="/list"', $output);
        $this->assertStringContainsString('data-action="click->window-close#close"', $output);
        // Without the `form-window` controller, no message value
        $this->assertStringNotContainsString('data-form-window-error-text-value', $output);
        $this->assertStringNotContainsString('data-form-window-network-error-text-value', $output);
    }

    public function testRenderForm(): void
    {
        $window = $this->buildFormWindow();
        $window->formView = $this->buildFormView(false);
        $window->formPath = '/object/new';

        $output = $this->render($window);

        $this->assertStringContainsString('action="/object/new"', $output);
        $this->assertStringContainsString('data-form-window-target="form"', $output);
        $this->assertStringContainsString('data-action="submit-&gt;form-window#submit"', $output);
        $this->assertStringContainsString('name="object[name]"', $output);
        $this->assertStringContainsString('[messages]app.object.name', $output);
        $this->assertStringNotContainsString('east-ux-fieldset', $output);
        $this->assertStringNotContainsString('form-window-wide', $output);
    }

    public function testRenderTranslatesTheActionsInTheEastUxDomain(): void
    {
        $window = $this->buildFormWindow();
        $window->formView = $this->buildFormView(false);

        $output = $this->render($window);

        $this->assertStringContainsString('[east_ux]teknoo.east.ux.form_window.action.save', $output);
        $this->assertStringContainsString('[east_ux]teknoo.east.ux.form_window.action.cancel', $output);
    }

    public function testRenderSubFormsInEastUxFieldsets(): void
    {
        $window = $this->buildFormWindow();
        $window->formView = $this->buildFormView(true);

        $output = $this->render($window);

        $this->assertStringContainsString('form-window-wide', $output);
        $this->assertStringContainsString('<div class="form-window-subforms">', $output);
        $this->assertEquals(2, substr_count($output, '<fieldset class="east-ux-fieldset border rounded p-3">'));
        $this->assertStringContainsString('[east_ux]teknoo.east.ux.form_window.fieldset.main', $output);
        // Labels of the subforms are supplied by the application: default domain
        $this->assertStringContainsString('[messages]app.object.address', $output);
        $this->assertStringContainsString('name="object[address][street]"', $output);
    }

    public function testRenderWithoutFormThemeKeepsTheGlobalThemes(): void
    {
        $window = $this->buildFormWindow(null);
        $window->formView = $this->buildFormView(false);

        $output = $this->render($window);

        $this->assertStringNotContainsString('test-theme-widget', $output);
        $this->assertStringContainsString('type="text"', $output);
    }

    public function testRenderWithFormThemeFromTheBundleConfiguration(): void
    {
        $window = $this->buildFormWindow(self::TEST_THEME);
        $window->formView = $this->buildFormView(false);

        $output = $this->render($window);

        $this->assertStringContainsString('<input class="test-theme-widget">', $output);
    }

    public function testRenderWithFormThemeFromTheProp(): void
    {
        $window = $this->buildFormWindow(null);
        $window->formTheme = self::TEST_THEME;
        $window->formView = $this->buildFormView(false);

        $output = $this->render($window);

        $this->assertStringContainsString('<input class="test-theme-widget">', $output);
    }
}
