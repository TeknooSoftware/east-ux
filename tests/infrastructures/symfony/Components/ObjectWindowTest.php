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

use ArrayObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Teknoo\East\UXBundle\Components\AbstractWindow;
use Teknoo\East\UXBundle\Components\ObjectWindow;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFunction;

use function dirname;
use function get_object_vars;

/**
 * Unit test for the Live window showing one persisted object, and for its template.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ObjectWindow::class)]
class ObjectWindowTest extends TestCase
{
    /**
     * The components rendered by the template, with their props: the `component()` function only records them
     *
     * @var ArrayObject<int, array{0: string, 1: array<string, mixed>}>
     */
    private ArrayObject $renderedComponents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderedComponents = new ArrayObject();
    }

    private function buildWindow(): ObjectWindow
    {
        return new ObjectWindow(new RequestStack());
    }

    private function render(ObjectWindow $window): string
    {
        // The templates of East UX are next to its components
        $abstractWindowFile = new ReflectionClass(AbstractWindow::class)->getFileName();
        $this->assertIsString($abstractWindowFile);

        $loader = new FilesystemLoader();
        $loader->addPath(dirname($abstractWindowFile, 2) . '/templates', 'TeknooEastUX');

        $twig = new Environment($loader, ['strict_variables' => true, 'cache' => false]);

        $renderedComponents = $this->renderedComponents;
        $twig->addFunction(
            new TwigFunction(
                'component',
                static function (string $name, array $props = []) use ($renderedComponents): string {
                    $renderedComponents->append([$name, $props]);

                    return '<component-' . $name . '/>';
                },
                ['is_safe' => ['html']],
            ),
        );

        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render(
            '@TeknooEastUX/components/ObjectWindow.html.twig',
            [
                ...get_object_vars($window),
                'this' => $window,
                'attributes' => new ComponentAttributes([], $escaper),
            ],
        );
    }

    public function testConstruct(): void
    {
        $window = $this->buildWindow();

        $this->assertInstanceOf(ObjectWindow::class, $window);
        $this->assertInstanceOf(AbstractWindow::class, $window);
    }

    public function testIsALiveComponent(): void
    {
        $attributes = new ReflectionClass(ObjectWindow::class)->getAttributes(AsLiveComponent::class);

        // No explicit name nor template: they come from the `twig_component.defaults` entry of the bundle
        $this->assertCount(1, $attributes);
        $this->assertSame([], $attributes[0]->getArguments());
    }

    public function testOriginalPathComesFromTheCurrentRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/items/42'));

        $window = new ObjectWindow($requestStack);

        $this->assertSame('/items/42', $window->originalPath);
    }

    public function testDefaultProperties(): void
    {
        $window = $this->buildWindow();

        $this->assertNull($window->originalPath);
        $this->assertFalse($window->hidden);
        $this->assertFalse($window->floating);
        $this->assertSame('', $window->contentComponent);
        $this->assertNull($window->objectInstance);
    }

    public function testWindowPropsAreLivePropsKeptAcrossReRenders(): void
    {
        foreach (
            [
                'title',
                'closable',
                'resizable',
                'draggable',
                'windowId',
                'closeRedirectUrl',
                'closeAriaLabel',
                'resizeAriaLabel',
            ] as $name
        ) {
            $property = new ReflectionProperty(ObjectWindow::class, $name);

            $this->assertSame(ObjectWindow::class, $property->getDeclaringClass()->getName());
            $this->assertTrue($property->isPublic());
            $this->assertCount(1, $property->getAttributes(LiveProp::class));
            // Not writable: the browser cannot change the window props of a rendered window
            $this->assertSame([], $property->getAttributes(LiveProp::class)[0]->getArguments());
            $this->assertSame(
                new ReflectionProperty(AbstractWindow::class, $name)->getDefaultValue(),
                $property->getDefaultValue(),
            );
        }
    }

    public function testHiddenIsAWritableLiveProp(): void
    {
        $property = new ReflectionProperty(ObjectWindow::class, 'hidden');

        $this->assertTrue($property->isPublic());
        $this->assertCount(1, $property->getAttributes(LiveProp::class));
        // Writable: the browser shows the window by setting it to false when it opens it
        $this->assertSame(['writable' => true], $property->getAttributes(LiveProp::class)[0]->getArguments());
        $this->assertFalse($property->getDefaultValue());
    }

    public function testFloatingAndContentComponentAreLiveProps(): void
    {
        foreach (['floating', 'contentComponent'] as $name) {
            $property = new ReflectionProperty(ObjectWindow::class, $name);

            $this->assertTrue($property->isPublic());
            $this->assertCount(1, $property->getAttributes(LiveProp::class));
            // Not writable: the browser cannot change the content of a rendered window
            $this->assertSame([], $property->getAttributes(LiveProp::class)[0]->getArguments());
        }
    }

    public function testObjectInstanceIsAWritablePublicPropertyAndNotALiveProp(): void
    {
        $property = new ReflectionProperty(ObjectWindow::class, 'objectInstance');

        // Filled by East Common's `LiveComponentBuilder` from the view parameter of the recipe, never by the browser
        $this->assertTrue($property->isPublic());
        $this->assertFalse($property->isReadOnly());
        $this->assertSame([], $property->getAttributes(LiveProp::class));
    }

    public function testCreateReference(): void
    {
        $reference = ObjectWindow::createReference();

        $this->assertSame(ObjectWindow::class, $reference->componentClass);
        $this->assertSame(['refresh', 'close'], $reference->methods);
    }

    public function testRefreshAndCloseAreLiveActions(): void
    {
        $this->assertCount(1, new ReflectionMethod(ObjectWindow::class, 'refresh')->getAttributes(LiveAction::class));
        $this->assertCount(1, new ReflectionMethod(ObjectWindow::class, 'close')->getAttributes(LiveAction::class));
    }

    public function testRefreshKeepsTheWindowShown(): void
    {
        $window = $this->buildWindow();

        $window->refresh();

        $this->assertFalse($window->hidden);
    }

    public function testCloseHidesTheWindow(): void
    {
        $window = $this->buildWindow();

        $window->close();

        $this->assertTrue($window->hidden);
    }

    public function testRenderAWindowOfAPageWithItsObject(): void
    {
        $object = new stdClass();

        $window = $this->buildWindow();
        $window->title = 'Items - View';
        $window->windowId = 'item-42';
        $window->closeRedirectUrl = '/items/';
        $window->contentComponent = 'ItemView';
        $window->objectInstance = $object;

        $output = $this->render($window);

        $this->assertStringContainsString('<div class="window h-100 d-flex flex-column"', $output);
        $this->assertStringContainsString('data-controller="list-window-refresh window-close"', $output);
        $this->assertStringContainsString('data-form-window="item-42"', $output);
        $this->assertStringContainsString('data-window-close-redirect-url-value="/items/"', $output);
        $this->assertStringContainsString('<h1 class="title">Items - View</h1>', $output);
        $this->assertStringContainsString(
            '<div class="window-pane flex-grow-1 overflow-auto p-3">',
            $output,
        );
        $this->assertStringContainsString('<component-ItemView/>', $output);
        $this->assertStringNotContainsString('window-drag', $output);
        $this->assertStringNotContainsString('window-floating', $output);
        $this->assertStringNotContainsString('d-none', $output);

        $this->assertSame(
            [['ItemView', ['object' => $object]]],
            $this->renderedComponents->getArrayCopy(),
        );
    }

    public function testRenderAHiddenFloatingWindowWithoutObjectIsEmpty(): void
    {
        $window = $this->buildWindow();
        $window->windowId = 'item-card-42';
        $window->floating = true;
        $window->hidden = true;
        $window->closable = true;
        $window->draggable = true;
        $window->contentComponent = 'ItemCard';

        $output = $this->render($window);

        $this->assertStringContainsString(
            '<div class="window draggable window-floating d-flex flex-column d-none"',
            $output,
        );
        $this->assertStringContainsString(
            'data-controller="list-window-refresh window-close window-drag"',
            $output,
        );
        $this->assertStringContainsString('data-action="mousedown->window-drag#startDrag"', $output);
        $this->assertStringContainsString('data-action="click->window-close#close"', $output);
        $this->assertStringNotContainsString('h-100', $output);
        $this->assertStringNotContainsString('<component-', $output);

        // Rendered by the component renderer, or refused by the recipe: no object, no content
        $this->assertCount(0, $this->renderedComponents);
    }
}
