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
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Twig\ComponentLexer;
use Teknoo\East\UXBundle\Components\AbstractWindow;
use Teknoo\East\UXBundle\Components\Window;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

use function dirname;
use function file_get_contents;
use function get_object_vars;
use function property_exists;

/**
 * Live Twig component rendering the window interface.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(Window::class)]
class WindowTest extends TestCase
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

    private function buildWindow(): Window
    {
        return new Window();
    }

    private function render(Window $window, ?string $content = null): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');

        $twig = new Environment($loader, ['strict_variables' => true, 'cache' => false]);
        $twig->setLexer(new ComponentLexer($twig));
        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        $variables = [
            ...get_object_vars($window),
            'this' => $window,
            'attributes' => new ComponentAttributes([], $escaper),
        ];

        // Symfony UX passes `content` only when the component receives it as a prop
        if (null !== $content) {
            $variables['content'] = $content;
        }

        return $twig->render('@TeknooEastUX/components/Window.html.twig', $variables);
    }

    public function testConstruct(): void
    {
        $window = $this->buildWindow();

        $this->assertInstanceOf(Window::class, $window);
        $this->assertInstanceOf(AbstractWindow::class, $window);
    }

    public function testIsATwigComponent(): void
    {
        $attributes = (new ReflectionClass(Window::class))->getAttributes(AsTwigComponent::class);

        // No explicit name nor template: they come from the `twig_component.defaults` entry of the bundle
        $this->assertCount(1, $attributes);
        $this->assertEquals([], $attributes[0]->getArguments());
    }

    public function testDeclaresStrictTypes(): void
    {
        $source = (string) file_get_contents((string) (new ReflectionClass(Window::class))->getFileName());

        $this->assertMatchesRegularExpression('#^declare\\(strict_types=1\\);$#m', $source);
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $window = $this->buildWindow();

        foreach (self::EAST_VIEW_PARAMETERS as $name) {
            $assignable = !property_exists($window, $name)
                || (($property = new ReflectionProperty($window, $name))->isPublic() && !$property->isReadOnly());

            $this->assertTrue($assignable, $name . ' must be a public and writable property or no property');
        }
    }

    public function testRenderDefault(): void
    {
        $window = $this->buildWindow();
        $window->title = 'My window';
        $window->windowId = 'my-window';

        $output = $this->render($window, 'Window content');

        $this->assertStringContainsString('<div class="window"', $output);
        $this->assertStringContainsString('data-controller="window-close"', $output);
        $this->assertStringContainsString('data-form-window="my-window"', $output);
        $this->assertStringContainsString('<h1 class="title">My window</h1>', $output);
        $this->assertStringContainsString('<button aria-label="Close" class="close hidden"></button>', $output);
        $this->assertStringContainsString('<button aria-label="Resize" class="resize hidden"></button>', $output);
        $this->assertStringContainsString('<div class="separator"></div>', $output);
        $this->assertStringContainsString('<div class="window-pane">', $output);
        $this->assertStringContainsString('Window content', $output);
        $this->assertStringNotContainsString('window-drag', $output);
    }

    public function testRenderClosableResizableDraggable(): void
    {
        $window = $this->buildWindow();
        $window->closable = true;
        $window->resizable = true;
        $window->draggable = true;
        $window->closeRedirectUrl = '/back';
        $window->closeAriaLabel = 'Close it';
        $window->resizeAriaLabel = 'Resize it';

        $output = $this->render($window);

        $this->assertStringContainsString('<div class="window draggable"', $output);
        $this->assertStringContainsString('data-controller="window-close window-drag"', $output);
        $this->assertStringContainsString('data-window-close-redirect-url-value="/back"', $output);
        $this->assertStringContainsString('data-action="mousedown->window-drag#startDrag"', $output);
        $this->assertStringContainsString(
            '<button aria-label="Close it" class="close" data-action="click->window-close#close"></button>',
            $output,
        );
        $this->assertStringContainsString('<button aria-label="Resize it" class="resize"></button>', $output);
    }

    public function testRenderWithoutContent(): void
    {
        $window = $this->buildWindow();
        $window->title = 'Empty window';

        // With `strict_variables` (Twig's default in debug mode), a missing `content` must not fail
        $output = $this->render($window);

        $this->assertStringContainsString('<h1 class="title">Empty window</h1>', $output);
        $this->assertMatchesRegularExpression('#<div class="window-pane">\s*</div>#', $output);
    }

    public function testRenderEscapesTitle(): void
    {
        $window = $this->buildWindow();
        $window->title = '<script>alert(1)</script>';

        $output = $this->render($window);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
    }
}
