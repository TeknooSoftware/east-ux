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
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\East\UX\Service\ThemeService;
use Teknoo\East\UXBundle\Components\ThemeSwitcher;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

use function array_intersect;
use function array_map;
use function dirname;
use function get_object_vars;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ThemeSwitcher::class)]
class ThemeSwitcherTest extends TestCase
{
    /**
     * View parameters of the East recipes, assigned by East Common's `LiveComponentBuilder` to the component
     * properties of the same name.
     */
    private const array EAST_VIEW_PARAMETERS = [
        'error',
        'form',
        'formView',
        'isTranslatable',
        'objectInstance',
        'objectSaved',
        'objectsCollection',
        'objectViewKey',
        'page',
        'pageCount',
        'queryParams',
        'request',
        'searchForm',
    ];

    private function buildSwitcher(
        ThemePreference $preference = new ThemePreference(Style::Legacy, Mode::Auto),
    ): ThemeSwitcher {
        return new ThemeSwitcher(new ThemeService($preference));
    }

    /**
     * Renders the template as Symfony UX does, with a translator returning `<key>|<domain>`
     *
     * @param array<string, string> $attributes
     */
    private function render(ThemeSwitcher $switcher, array $attributes = []): Crawler
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');

        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        $twig->addExtension(
            new TranslationExtension(
                new class () implements TranslatorInterface {
                    /**
                     * @param array<string, mixed> $parameters
                     */
                    public function trans(
                        string $id,
                        array $parameters = [],
                        ?string $domain = null,
                        ?string $locale = null,
                    ): string {
                        return $id . '|' . ($domain ?? 'default');
                    }

                    public function getLocale(): string
                    {
                        return 'en';
                    }
                }
            )
        );

        return new Crawler(
            $twig->render(
                '@TeknooEastUX/components/ThemeSwitcher.html.twig',
                [
                    'this' => $switcher,
                    'attributes' => new ComponentAttributes($attributes, $escaper),
                ] + get_object_vars($switcher),
            ),
        );
    }

    public function testIsAPlainTwigComponent(): void
    {
        $reflection = new ReflectionClass(ThemeSwitcher::class);

        $this->assertCount(1, $reflection->getAttributes(AsTwigComponent::class));
        $this->assertCount(0, $reflection->getAttributes(AsLiveComponent::class));
    }

    public function testDefaultProperties(): void
    {
        $switcher = $this->buildSwitcher();

        $this->assertTrue($switcher->showMode);
        $this->assertTrue($switcher->showStyle);
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $nonPublicProperties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(ThemeSwitcher::class))->getProperties(
                ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE,
            ),
        );

        $this->assertSame([], array_intersect($nonPublicProperties, self::EAST_VIEW_PARAMETERS));
    }

    public function testGetPreference(): void
    {
        $preference = new ThemePreference(Style::Modern, Mode::Light);

        $this->assertSame($preference, $this->buildSwitcher($preference)->getPreference());
    }

    public function testRender(): void
    {
        $crawler = $this->render($this->buildSwitcher(), ['class' => 'ms-lg-3', 'id' => 'theme']);

        $root = $crawler->filter('div.east-ux-theme-switcher');
        $this->assertCount(1, $root);
        $this->assertSame('theme', $root->attr('id'));
        $this->assertStringContainsString('ms-lg-3', (string) $root->attr('class'));
        $this->assertSame('group', $root->attr('role'));
        $this->assertSame('teknoo.east.ux.theme_switcher.group.theme|east_ux', $root->attr('aria-label'));

        $groups = $crawler->filter('div.btn-group');
        $this->assertSame(
            ['teknoo.east.ux.theme_switcher.group.mode|east_ux', 'teknoo.east.ux.theme_switcher.group.style|east_ux'],
            $groups->each(static fn (Crawler $group): ?string => $group->attr('aria-label')),
        );

        $buttons = $crawler->filter('button');
        $this->assertSame(
            [
                'mode=light teknoo.east.ux.theme_switcher.mode.light|east_ux false',
                'mode=dark teknoo.east.ux.theme_switcher.mode.dark|east_ux false',
                'mode=auto teknoo.east.ux.theme_switcher.mode.auto|east_ux true',
                'style=legacy teknoo.east.ux.theme_switcher.style.legacy|east_ux true',
                'style=modern teknoo.east.ux.theme_switcher.style.modern|east_ux false',
            ],
            $buttons->each(
                static fn (Crawler $button): string => (
                    null !== $button->attr('data-theme-mode-param')
                        ? 'mode=' . $button->attr('data-theme-mode-param')
                        : 'style=' . $button->attr('data-theme-style-param')
                ) . ' ' . $button->text() . ' ' . $button->attr('aria-pressed'),
            ),
        );

        foreach ($buttons as $button) {
            $this->assertSame('button', $button->getAttribute('type'));
            $this->assertSame('click->theme#select', $button->getAttribute('data-action'));
            $this->assertSame('option', $button->getAttribute('data-theme-target'));
        }

        $this->assertCount(2, $crawler->filter('button.active[aria-pressed="true"]'));
    }

    public function testRenderPressesTheButtonsOfThePreference(): void
    {
        $crawler = $this->render($this->buildSwitcher(new ThemePreference(Style::Modern, Mode::Dark)));

        $this->assertSame(
            ['dark', 'modern'],
            $crawler->filter('button.active[aria-pressed="true"]')->each(
                static fn (Crawler $button): string => (string) (
                    $button->attr('data-theme-mode-param') ?? $button->attr('data-theme-style-param')
                ),
            ),
        );
    }

    public function testRenderOnlyTheModes(): void
    {
        $switcher = $this->buildSwitcher();
        $switcher->showStyle = false;

        $crawler = $this->render($switcher);

        $this->assertCount(1, $crawler->filter('div.btn-group'));
        $this->assertCount(3, $crawler->filter('button[data-theme-mode-param]'));
        $this->assertCount(0, $crawler->filter('button[data-theme-style-param]'));
    }

    public function testRenderOnlyTheStyles(): void
    {
        $switcher = $this->buildSwitcher();
        $switcher->showMode = false;

        $crawler = $this->render($switcher);

        $this->assertCount(1, $crawler->filter('div.btn-group'));
        $this->assertCount(0, $crawler->filter('button[data-theme-mode-param]'));
        $this->assertCount(2, $crawler->filter('button[data-theme-style-param]'));
    }
}
