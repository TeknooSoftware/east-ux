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
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Teknoo\East\Common\Contracts\User\UserInterface;
use Teknoo\East\Common\Object\User;
use Teknoo\East\UX\Contracts\Menu\MenuGeneratorInterface;
use Teknoo\East\UX\Contracts\Enum\Menu\Location;
use Teknoo\East\UX\Object\DTO\Menu\MenuItem;
use Teknoo\East\UX\Object\DTO\Menu\MenuSection;
use Teknoo\East\UX\Service\MenuService;
use Teknoo\East\UXBundle\Components\MenuBar;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

use function array_intersect;
use function array_map;
use function dirname;
use function get_object_vars;
use function http_build_query;
use function preg_replace;
use function strpos;
use function substr_count;
use function trim;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(MenuBar::class)]
class MenuBarTest extends TestCase
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

    private function buildMenuBar(?MenuService $menuService = null, ?string $homeRoute = null): MenuBar
    {
        return new MenuBar($menuService ?? $this->createStub(MenuService::class), $homeRoute);
    }

    private function buildUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = []): string {
                if (empty($parameters)) {
                    return '/' . $name;
                }

                return '/' . $name . '?' . http_build_query($parameters);
            }
        );

        return $urlGenerator;
    }

    /**
     * Renders the template (or `$template`, a template extending it) as Symfony UX does (`this`, `attributes` and
     * the public props), with a translator returning `<key>|<domain>` (`default` when no domain is passed). Spaces
     * between tags are collapsed.
     *
     * @param array<string, string> $templates
     */
    private function render(
        MenuBar $menuBar,
        ?UrlGeneratorInterface $urlGenerator = null,
        string $template = '@TeknooEastUX/components/MenuBar.html.twig',
        array $templates = [],
    ): string {
        $filesystemLoader = new FilesystemLoader();
        $filesystemLoader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');

        $twig = new Environment(
            new ChainLoader([new ArrayLoader($templates), $filesystemLoader]),
            ['strict_variables' => true, 'autoescape' => 'html'],
        );
        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        $twig->addExtension(new RoutingExtension($urlGenerator ?? $this->buildUrlGenerator()));
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

        $html = $twig->render(
            $template,
            [
                'this' => $menuBar,
                'attributes' => new ComponentAttributes([], $escaper),
            ] + get_object_vars($menuBar),
        );

        return trim((string) preg_replace('/\s+/', ' ', $html));
    }

    private function buildMenuServiceWithSections(): MenuService
    {
        $administration = new MenuSection(name: 'app.menu.administration', position: 1, icon: 'icon-gear');
        $administration->addItem(
            new MenuItem(name: 'app.menu.users', route: 'app_users', routeParameters: ['page' => 2], icon: 'icon-users')
        );
        $administration->addItem(new MenuItem(name: 'app.menu.notes', route: 'app_notes'));

        $tasks = new MenuSection(name: 'app.menu.tasks', position: 2);
        $tasks->addItem(new MenuItem(name: 'app.menu.word_count', route: 'app_word_count'));

        $userSection = new MenuSection(name: 'Jane <Doe>', position: 1, rawLabel: true);
        $userSection->addItem(
            new MenuItem(name: 'app.menu.my_settings', route: 'app_settings', icon: 'icon-user')
        );
        $userSection->addItem(new MenuItem(name: '', route: '', divider: true));
        $userSection->addItem(new MenuItem(name: 'Profile of <Jane>', route: 'app_profile', rawLabel: true));
        $userSection->addItem(new MenuItem(name: '', route: '', divider: true));
        $userSection->addItem(new MenuItem(name: 'app.menu.logout', route: 'app_logout'));

        $menuService = $this->createStub(MenuService::class);
        $menuService->method('getMenuFor')->willReturnCallback(
            static fn (UserInterface $user, Location $location): array => match ($location) {
                Location::TopLeft => [$administration, $tasks],
                Location::TopRight => [$userSection],
            }
        );

        return $menuService;
    }

    public function testConstruct(): void
    {
        $menuBar = $this->buildMenuBar();

        $this->assertInstanceOf(MenuBar::class, $menuBar);
    }

    public function testIsAPlainTwigComponent(): void
    {
        $reflection = new ReflectionClass(MenuBar::class);

        $this->assertCount(1, $reflection->getAttributes(AsTwigComponent::class));
        $this->assertCount(0, $reflection->getAttributes(AsLiveComponent::class));
    }

    public function testDefaultUserIsNull(): void
    {
        $menuBar = $this->buildMenuBar();

        $this->assertNull($menuBar->user);
    }

    public function testGetHomeRoute(): void
    {
        $this->assertNull($this->buildMenuBar()->getHomeRoute());
        $this->assertSame('app_home', $this->buildMenuBar(homeRoute: 'app_home')->getHomeRoute());
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $nonPublicProperties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(MenuBar::class))->getProperties(
                ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE,
            ),
        );

        $this->assertSame([], array_intersect($nonPublicProperties, self::EAST_VIEW_PARAMETERS));
    }

    public function testGetRightItemsWithNullUser(): void
    {
        $menuService = $this->createMock(MenuService::class);
        $menuService->expects($this->never())->method('getMenuFor');

        $menuBar = $this->buildMenuBar($menuService);
        $menuBar->user = null;

        $this->assertSame([], $menuBar->getRightItems());
    }

    public function testGetRightItemsDelegatesToMenuService(): void
    {
        $user = new User();

        $sections = [new MenuSection(name: 'section', position: 0)];

        $menuService = $this->createMock(MenuService::class);
        $menuService
            ->expects($this->once())
            ->method('getMenuFor')
            ->with($user, Location::TopRight)
            ->willReturn($sections);

        $menuBar = $this->buildMenuBar($menuService);
        $menuBar->user = $user;

        $this->assertSame($sections, $menuBar->getRightItems());
    }

    public function testGetLeftItemsWithNullUser(): void
    {
        $menuService = $this->createMock(MenuService::class);
        $menuService->expects($this->never())->method('getMenuFor');

        $menuBar = $this->buildMenuBar($menuService);
        $menuBar->user = null;

        $this->assertSame([], $menuBar->getLeftItems());
    }

    public function testGetLeftItemsDelegatesToMenuService(): void
    {
        $user = new User();

        $sections = [new MenuSection(name: 'section', position: 0)];

        $menuService = $this->createMock(MenuService::class);
        $menuService
            ->expects($this->once())
            ->method('getMenuFor')
            ->with($user, Location::TopLeft)
            ->willReturn($sections);

        $menuBar = $this->buildMenuBar($menuService);
        $menuBar->user = $user;

        $this->assertSame($sections, $menuBar->getLeftItems());
    }

    public function testRenderWithoutUserNorHomeRoute(): void
    {
        $menuService = $this->createMock(MenuService::class);
        $menuService->expects($this->never())->method('getMenuFor');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $html = $this->render($this->buildMenuBar($menuService), $urlGenerator);

        $this->assertStringStartsWith('<nav class="navbar navbar-expand-lg fixed-top">', $html);
        $this->assertStringContainsString(
            '<a class="navbar-brand" href="/"> teknoo.east.ux.menu_bar.brand.title|east_ux </a>',
            $html,
        );
        $this->assertStringContainsString('<ul class="navbar-nav me-auto"> </ul>', $html);
        $this->assertStringContainsString('<ul class="navbar-nav ms-auto"> </ul>', $html);
        $this->assertStringNotContainsString('dropdown', $html);
    }

    public function testRenderBrandLinkFromHomeRoute(): void
    {
        $html = $this->render($this->buildMenuBar(homeRoute: 'app_home'));

        $this->assertStringContainsString('<a class="navbar-brand" href="/app_home">', $html);
    }

    public function testRenderBrandBlockIsOverridable(): void
    {
        $html = $this->render(
            menuBar: $this->buildMenuBar(homeRoute: 'app_home'),
            template: 'app_menu_bar.html.twig',
            templates: [
                'app_menu_bar.html.twig' => "{% extends '@TeknooEastUX/components/MenuBar.html.twig' %}"
                    . '{% block brand %}<span class="brand-icon"></span> My App{% endblock %}',
            ],
        );

        $this->assertStringContainsString(
            '<a class="navbar-brand" href="/app_home"> <span class="brand-icon"></span> My App </a>',
            $html,
        );
        $this->assertStringNotContainsString('teknoo.east.ux.menu_bar.brand.title', $html);
    }

    public function testRenderSectionsOfTheUser(): void
    {
        $menuBar = $this->buildMenuBar($this->buildMenuServiceWithSections());
        $menuBar->user = new User();

        $html = $this->render($menuBar);

        $left = (int) strpos($html, '<ul class="navbar-nav me-auto">');
        $right = (int) strpos($html, '<ul class="navbar-nav ms-auto">');
        $this->assertGreaterThan(0, $left);
        $this->assertGreaterThan($left, $right);

        // Left sections, translated in the default domain, in their order
        $administration = (int) strpos($html, 'app.menu.administration|default');
        $tasks = (int) strpos($html, 'app.menu.tasks|default');
        $this->assertGreaterThan($left, $administration);
        $this->assertGreaterThan($administration, $tasks);
        $this->assertGreaterThan($tasks, $right);
        $this->assertStringContainsString(
            '<a class="nav-link dropdown-toggle" href="#" id="menuBarLeftDropdown1" role="button" '
            . 'data-bs-toggle="dropdown"> <span class="menu-icon icon-gear" aria-hidden="true"></span> '
            . 'app.menu.administration|default </a>',
            $html,
        );
        $this->assertStringContainsString('id="menuBarLeftDropdown2"', $html);
        $this->assertStringContainsString(
            '<li><a class="dropdown-item" href="/app_users?page=2"><span class="menu-icon icon-users" '
            . 'aria-hidden="true"></span> app.menu.users|default</a></li>',
            $html,
        );
        $this->assertStringContainsString(
            '<li><a class="dropdown-item" href="/app_notes">app.menu.notes|default</a></li>',
            $html,
        );
        $this->assertStringContainsString(
            '<li><a class="dropdown-item" href="/app_word_count">app.menu.word_count|default</a></li>',
            $html,
        );

        // Right section, with a raw label (escaped, not translated), dividers and raw items
        $this->assertGreaterThan($right, (int) strpos($html, 'Jane &lt;Doe&gt;'));
        $this->assertStringContainsString(
            'id="menuBarRightDropdown1" role="button" data-bs-toggle="dropdown"> Jane &lt;Doe&gt; </a>',
            $html,
        );
        $this->assertStringNotContainsString('Jane &lt;Doe&gt;|default', $html);
        $this->assertSame(2, substr_count($html, '<li><hr class="dropdown-divider"></li>'));
        $this->assertStringContainsString(
            '<li><a class="dropdown-item" href="/app_profile">Profile of &lt;Jane&gt;</a></li>',
            $html,
        );
        $this->assertStringContainsString(
            '<li><a class="dropdown-item" href="/app_logout">app.menu.logout|default</a></li>',
            $html,
        );

        // Nothing hard-coded anymore
        $this->assertStringNotContainsString('<li class="vr">', $html);
        $this->assertStringNotContainsString('contact', $html);
        $this->assertStringNotContainsString('<a class="nav-link" href="#">', $html);
    }

    public function testRenderButtonsAttributesAndTranslationDomains(): void
    {
        $theme = new MenuSection(name: 'east.theme', position: 1, icon: 'icon-palette', translationDomain: 'east_ux');
        $theme->addItem(
            new MenuItem(
                name: 'east.theme.dark',
                route: '',
                attributes: ['data-action' => 'click->theme#select', 'data-theme-mode-param' => 'dark'],
                translationDomain: 'east_ux',
            )
        );
        $theme->addItem(
            new MenuItem(
                name: 'app.menu.help',
                route: 'app_help',
                icon: 'icon-help',
                attributes: ['data-turbo' => 'false', 'title' => 'Help "now" <b>'],
            )
        );
        $theme->addItem(new MenuItem(name: 'Raw <button>', route: '', rawLabel: true));

        $menuService = $this->createStub(MenuService::class);
        $menuService->method('getMenuFor')->willReturnCallback(
            static fn (UserInterface $user, Location $location): array => match ($location) {
                Location::TopLeft => [],
                Location::TopRight => [$theme],
            }
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->with('app_help')->willReturn('/app_help');

        $menuBar = $this->buildMenuBar($menuService);
        $menuBar->user = new User();

        $html = $this->render($menuBar, $urlGenerator);

        // The section is translated in its domain
        $this->assertStringContainsString(
            'data-bs-toggle="dropdown"> <span class="menu-icon icon-palette" aria-hidden="true"></span> '
            . 'east.theme|east_ux </a>',
            $html,
        );
        // An item without route is a button, with its attributes and translated in its domain
        $this->assertStringContainsString(
            '<li><button type="button" class="dropdown-item" data-action="click-&gt;theme#select" '
            . 'data-theme-mode-param="dark">east.theme.dark|east_ux</button></li>',
            $html,
        );
        // The attributes of a link are escaped, its name is translated in the default domain
        $this->assertStringContainsString(
            '<li><a class="dropdown-item" href="/app_help" data-turbo="false" '
            . 'title="Help &quot;now&quot; &lt;b&gt;"><span class="menu-icon icon-help" aria-hidden="true"></span> '
            . 'app.menu.help|default</a></li>',
            $html,
        );
        $this->assertStringContainsString(
            '<li><button type="button" class="dropdown-item">Raw &lt;button&gt;</button></li>',
            $html,
        );
    }

    public function testRenderWithMenuServiceAndGenerators(): void
    {
        $generator = $this->createStub(MenuGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static function (MenuService $menuService, Location $location, UserInterface $user, array $roles): void {
                if (Location::TopRight === $location) {
                    $menuService->getSection(name: (string) $user, location: $location, rawLabel: true)
                        ->addItem(new MenuItem(name: 'app.menu.logout', route: 'app_logout'));

                    return;
                }

                $menuService->getSection(name: 'app.menu.tasks', location: $location)
                    ->addItem(new MenuItem(name: 'app.menu.word_count', route: 'app_word_count'));
            }
        );

        $user = new User();
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $user->setEmail('jane@example.com');

        $menuBar = $this->buildMenuBar(new MenuService([$generator]), 'app_home');
        $menuBar->user = $user;

        $html = $this->render($menuBar);

        $this->assertStringContainsString('id="menuBarLeftDropdown1" role="button" data-bs-toggle="dropdown"> '
            . 'app.menu.tasks|default </a>', $html);
        $this->assertStringContainsString(
            '<li><a class="dropdown-item" href="/app_word_count">app.menu.word_count|default</a></li>',
            $html,
        );
        $this->assertStringContainsString('id="menuBarRightDropdown1" role="button" data-bs-toggle="dropdown"> '
            . 'Jane Doe </a>', $html);
        $this->assertStringContainsString(
            '<li><a class="dropdown-item" href="/app_logout">app.menu.logout|default</a></li>',
            $html,
        );
    }
}
