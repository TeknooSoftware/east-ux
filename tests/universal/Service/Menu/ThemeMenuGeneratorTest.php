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

namespace Teknoo\Tests\East\UX\Service\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Teknoo\East\Common\Contracts\User\UserInterface;
use Teknoo\East\UX\Contracts\Enum\Menu\Location;
use Teknoo\East\UX\Contracts\Menu\MenuGeneratorInterface;
use Teknoo\East\UX\Object\DTO\Menu\MenuItem;
use Teknoo\East\UX\Object\DTO\Menu\MenuSection;
use Teknoo\East\UX\Service\Menu\ThemeMenuGenerator;
use Teknoo\East\UX\Service\MenuService;

use function array_map;

/**
 * Unit test for the menu generator of the theme switcher.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ThemeMenuGenerator::class)]
class ThemeMenuGeneratorTest extends TestCase
{
    private function buildUser(): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user1');
        $user->method('getRoles')->willReturn([]);

        return $user;
    }

    /**
     * Entries of a section: `---` for a divider, `<name> <param>=<value>` for a theme button
     *
     * @return list<string>
     */
    private function describe(MenuSection $section): array
    {
        return array_map(
            static function (MenuItem $item): string {
                if ($item->divider) {
                    return '---';
                }

                $attributes = $item->attributes;
                unset(
                    $attributes['data-action'],
                    $attributes['data-theme-target'],
                    $attributes['role'],
                    $attributes['aria-checked'],
                );

                $description = $item->name;
                foreach ($attributes as $name => $value) {
                    $description .= ' ' . $name . '=' . $value;
                }

                return $description;
            },
            $section->getItems(),
        );
    }

    public function testIsAMenuGenerator(): void
    {
        $this->assertInstanceOf(MenuGeneratorInterface::class, new ThemeMenuGenerator());
    }

    public function testGenerateAddsItsSectionOnTheRight(): void
    {
        $service = new MenuService([new ThemeMenuGenerator()]);

        $this->assertSame([], $service->getMenuFor($this->buildUser(), Location::TopLeft));

        $sections = $service->getMenuFor($this->buildUser(), Location::TopRight);
        $this->assertCount(1, $sections);
        $this->assertSame('teknoo.east.ux.theme_switcher.group.theme', $sections[0]->name);
        $this->assertSame('east_ux', $sections[0]->translationDomain);
        $this->assertSame('', $sections[0]->icon);
        $this->assertFalse($sections[0]->rawLabel);

        $this->assertSame(
            [
                'teknoo.east.ux.theme_switcher.mode.light data-theme-mode-param=light',
                'teknoo.east.ux.theme_switcher.mode.dark data-theme-mode-param=dark',
                'teknoo.east.ux.theme_switcher.mode.auto data-theme-mode-param=auto',
                '---',
                'teknoo.east.ux.theme_switcher.style.legacy data-theme-style-param=legacy',
                'teknoo.east.ux.theme_switcher.style.modern data-theme-style-param=modern',
            ],
            $this->describe($sections[0]),
        );
    }

    public function testItemsAreButtonsOfTheThemeController(): void
    {
        $section = new MenuSection('section', 1);
        new ThemeMenuGenerator()->addItemsTo($section);

        foreach ($section->getItems() as $item) {
            if ($item->divider) {
                continue;
            }

            $this->assertSame('', $item->route);
            $this->assertSame('east_ux', $item->translationDomain);
            $this->assertFalse($item->rawLabel);
            $this->assertSame('click->theme#select', $item->attributes['data-action'] ?? null);
            $this->assertSame('menuOption', $item->attributes['data-theme-target'] ?? null);
            $this->assertSame('menuitemradio', $item->attributes['role'] ?? null);
            $this->assertSame('false', $item->attributes['aria-checked'] ?? null);
        }
    }

    public function testGenerateOnAnotherLocationWithAnIcon(): void
    {
        $service = new MenuService([new ThemeMenuGenerator(location: Location::TopLeft, icon: 'icon-palette')]);

        $this->assertSame([], $service->getMenuFor($this->buildUser(), Location::TopRight));

        $sections = $service->getMenuFor($this->buildUser(), Location::TopLeft);
        $this->assertCount(1, $sections);
        $this->assertSame('icon-palette', $sections[0]->icon);
        $this->assertCount(6, $sections[0]->getItems());
    }

    public function testOnlyTheModes(): void
    {
        $section = new MenuSection('section', 1);
        new ThemeMenuGenerator(withStyle: false)->addItemsTo($section);

        $this->assertSame(
            [
                'teknoo.east.ux.theme_switcher.mode.light data-theme-mode-param=light',
                'teknoo.east.ux.theme_switcher.mode.dark data-theme-mode-param=dark',
                'teknoo.east.ux.theme_switcher.mode.auto data-theme-mode-param=auto',
            ],
            $this->describe($section),
        );
    }

    public function testOnlyTheStyles(): void
    {
        $section = new MenuSection('section', 1);
        new ThemeMenuGenerator(withMode: false)->addItemsTo($section);

        $this->assertSame(
            [
                'teknoo.east.ux.theme_switcher.style.legacy data-theme-style-param=legacy',
                'teknoo.east.ux.theme_switcher.style.modern data-theme-style-param=modern',
            ],
            $this->describe($section),
        );
    }

    public function testNoSectionWithoutModesNorStyles(): void
    {
        $service = new MenuService([new ThemeMenuGenerator(withMode: false, withStyle: false)]);

        $this->assertSame([], $service->getMenuFor($this->buildUser(), Location::TopRight));
    }

    public function testAddItemsToAnExistingSectionOfTheApplication(): void
    {
        $section = new MenuSection(name: 'Jane Doe', position: 1, rawLabel: true);
        $section->addItem(new MenuItem(name: 'app.menu.settings', route: 'app_settings'));

        $generator = new ThemeMenuGenerator();
        $this->assertSame($generator, $generator->addItemsTo($section));

        $items = $section->getItems();
        $this->assertCount(7, $items);
        $this->assertSame('app.menu.settings', $items[0]->name);
        $this->assertSame('teknoo.east.ux.theme_switcher.mode.light', $items[1]->name);
        $this->assertSame('teknoo.east.ux.theme_switcher.style.modern', $items[6]->name);
    }
}
