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

namespace Teknoo\Tests\East\UXBundle\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\East\UX\Service\ThemeService;
use Teknoo\East\UXBundle\Twig\ThemeExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

use function array_map;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ThemeExtension::class)]
class ThemeExtensionTest extends TestCase
{
    private function buildUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                '_teknoo_east_ux_theme_update' => '/east-ux/theme',
                default => throw new RouteNotFoundException($name),
            },
        );

        return $urlGenerator;
    }

    private function buildExtension(
        ThemePreference $preference = new ThemePreference(Style::Legacy, Mode::Auto),
        string $storage = 'session',
        ?string $updateRoute = '_teknoo_east_ux_theme_update',
        ?UrlGeneratorInterface $urlGenerator = null,
    ): ThemeExtension {
        return new ThemeExtension(
            new ThemeService($preference),
            $urlGenerator ?? $this->buildUrlGenerator(),
            $storage,
            $updateRoute,
        );
    }

    public function testFunctions(): void
    {
        $functions = $this->buildExtension()->getFunctions();

        $this->assertSame(
            ['east_ux_theme', 'east_ux_theme_class', 'east_ux_theme_attributes', 'east_ux_theme_script'],
            array_map(static fn (TwigFunction $function): string => $function->getName(), $functions),
        );
    }

    public function testGetPreference(): void
    {
        $extension = $this->buildExtension(new ThemePreference(Style::Modern, Mode::Dark));

        $this->assertEquals(new ThemePreference(Style::Modern, Mode::Dark), $extension->getPreference());
        $this->assertEquals(
            new ThemePreference(Style::Legacy, Mode::Light),
            $extension->getPreference('legacy', 'light'),
        );
        $this->assertEquals(
            new ThemePreference(Style::Legacy, Mode::Auto),
            $extension->getPreference(Style::Legacy, Mode::Auto),
        );
        // Invalid values are replaced by the preference of the visitor
        $this->assertEquals(
            new ThemePreference(Style::Modern, Mode::Dark),
            $extension->getPreference('retro', 'sepia'),
        );
    }

    public function testGetClass(): void
    {
        $this->assertSame('', $this->buildExtension()->getClass());
        $this->assertSame('system-modern', $this->buildExtension()->getClass('modern'));
        $this->assertSame(
            'system-modern',
            $this->buildExtension(new ThemePreference(Style::Modern, Mode::Light))->getClass(),
        );
        $this->assertSame(
            '',
            $this->buildExtension(new ThemePreference(Style::Modern, Mode::Light))->getClass(Style::Legacy),
        );
    }

    public function testRenderAttributesWithTheSessionStorage(): void
    {
        $this->assertSame(
            'data-controller="theme" data-theme-style-value="legacy" data-theme-mode-value="auto" '
            . 'data-theme-storage-value="session" data-theme-url-value="/east-ux/theme"',
            $this->buildExtension()->renderAttributes(),
        );
    }

    public function testRenderAttributesOfTheBootstrapTheme(): void
    {
        $extension = $this->buildExtension();

        $this->assertStringNotContainsString('data-bs-theme', $extension->renderAttributes('legacy', 'light'));
        $this->assertStringStartsWith('data-bs-theme="dark" ', $extension->renderAttributes('legacy', 'dark'));
        $this->assertStringStartsWith('data-bs-theme="light" ', $extension->renderAttributes('modern', 'light'));
        $this->assertStringStartsWith('data-bs-theme="dark" ', $extension->renderAttributes('modern', 'dark'));
        // Auto is resolved by the browser, the server renders it as light
        $this->assertStringStartsWith('data-bs-theme="light" ', $extension->renderAttributes('modern', 'auto'));
        $this->assertStringContainsString(
            'data-theme-style-value="modern" data-theme-mode-value="auto"',
            $extension->renderAttributes('modern', 'auto'),
        );
    }

    public function testRenderAttributesWithAnUrlOfTheApplication(): void
    {
        $this->assertStringEndsWith(
            'data-theme-storage-value="session" data-theme-url-value="/app/theme?a=1&amp;b=&quot;2&quot;"',
            $this->buildExtension()->renderAttributes(url: '/app/theme?a=1&b="2"'),
        );
    }

    public function testRenderAttributesWithoutTheRoutesOfTheTheme(): void
    {
        $this->assertStringEndsWith(
            'data-theme-storage-value="session"',
            $this->buildExtension(updateRoute: 'missing_route')->renderAttributes(),
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $this->assertStringEndsWith(
            'data-theme-storage-value="session"',
            $this->buildExtension(updateRoute: null, urlGenerator: $urlGenerator)->renderAttributes(),
        );
    }

    public function testRenderAttributesWithTheBrowserStorage(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $extension = $this->buildExtension(storage: 'browser', urlGenerator: $urlGenerator);

        $this->assertSame(
            'data-controller="theme" data-theme-style-value="legacy" data-theme-mode-value="auto" '
            . 'data-theme-storage-value="browser"',
            $extension->renderAttributes(),
        );
        // An application keeping the choice in its database is notified of the changes
        $this->assertStringEndsWith(
            'data-theme-storage-value="browser" data-theme-url-value="/app/theme"',
            $extension->renderAttributes(url: '/app/theme'),
        );
        $this->assertStringEndsWith('data-theme-storage-value="browser"', $extension->renderAttributes(url: ''));
    }

    public function testRenderScript(): void
    {
        $script = $this->buildExtension()->renderScript();

        $this->assertStringStartsWith('<script>', $script);
        $this->assertStringEndsWith('</script>', $script);
        $this->assertStringContainsString("matchMedia('(prefers-color-scheme: dark)')", $script);
        $this->assertStringContainsString("localStorage.getItem(key + ':style')", $script);
        $this->assertStringContainsString("root.classList.toggle('system-modern', 'modern' === style);", $script);
        $this->assertStringContainsString('(document.documentElement);', $script);
    }

    public function testRenderScriptWithANonce(): void
    {
        $extension = $this->buildExtension();

        $this->assertStringStartsWith('<script nonce="abc123">', $extension->renderScript('abc123'));
        $this->assertStringStartsWith('<script nonce="a&quot;b">', $extension->renderScript('a"b'));
        $this->assertStringStartsWith('<script>', $extension->renderScript(''));
    }

    public function testRenderedInATemplate(): void
    {
        $twig = new Environment(
            new ArrayLoader([
                'layout.html.twig' => '<html lang="en" class="{{ east_ux_theme_class() }}" '
                    . '{{ east_ux_theme_attributes() }}><head>{{ east_ux_theme_script("n0nce") }}</head>'
                    . '{{ east_ux_theme().mode.value }}</html>',
            ]),
            ['strict_variables' => true, 'autoescape' => 'html'],
        );
        $twig->addExtension($this->buildExtension(new ThemePreference(Style::Modern, Mode::Dark)));

        $html = $twig->render('layout.html.twig');

        // Rendered as HTML, not escaped
        $this->assertStringStartsWith(
            '<html lang="en" class="system-modern" data-bs-theme="dark" data-controller="theme" '
            . 'data-theme-style-value="modern" data-theme-mode-value="dark" data-theme-storage-value="session" '
            . 'data-theme-url-value="/east-ux/theme"><head><script nonce="n0nce">',
            $html,
        );
        $this->assertStringEndsWith('</script></head>dark</html>', $html);
    }
}
