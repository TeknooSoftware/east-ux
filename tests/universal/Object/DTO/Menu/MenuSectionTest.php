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

namespace Teknoo\Tests\East\UX\Object\DTO\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Teknoo\East\UX\Object\DTO\Menu\MenuItem;
use Teknoo\East\UX\Object\DTO\Menu\MenuSection;
use Teknoo\East\UX\Contracts\Enum\Menu\Sorting;

/**
 * Data transfer object for menu section data.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(MenuSection::class)]
class MenuSectionTest extends TestCase
{
    public function testConstruct(): void
    {
        $section = new MenuSection(name: 'section.title', position: 5);

        $this->assertInstanceOf(MenuSection::class, $section);
        $this->assertSame('section.title', $section->name);
        $this->assertSame(5, $section->position);
        $this->assertSame('', $section->icon);
        $this->assertFalse($section->rawLabel);
        $this->assertSame([], $section->getItems());
    }

    public function testRawLabel(): void
    {
        $section = new MenuSection(name: 'John Doe', position: 1, icon: 'icon-user', rawLabel: true);

        $this->assertSame('John Doe', $section->name);
        $this->assertSame('icon-user', $section->icon);
        $this->assertTrue($section->rawLabel);
    }

    public function testAddItemAppend(): void
    {
        $section = new MenuSection(name: 'title', position: 0);

        $item1 = new MenuItem(name: 'item.one', route: 'route_one');
        $item2 = new MenuItem(name: 'item.two', route: 'route_two');

        $section->addItem($item1);
        $section->addItem($item2);

        $items = $section->getItems();
        $this->assertCount(2, $items);
        $this->assertSame($item1, $items[0]);
        $this->assertSame($item2, $items[1]);
    }

    public function testAddItemPrepend(): void
    {
        $section = new MenuSection(name: 'title', position: 0);

        $item1 = new MenuItem(name: 'item.one', route: 'route_one');
        $item2 = new MenuItem(name: 'item.two', route: 'route_two');

        $section->addItem($item1, Sorting::Prepend);
        $section->addItem($item2, Sorting::Prepend);

        $items = $section->getItems();
        $this->assertCount(2, $items);
        $this->assertSame($item2, $items[0]);
        $this->assertSame($item1, $items[1]);
    }

    public function testAddItemKeepsDividers(): void
    {
        $section = new MenuSection(name: 'title', position: 0);

        $item1 = new MenuItem(name: 'item.one', route: 'route_one');
        $divider = new MenuItem(name: '', route: '', divider: true);
        $item2 = new MenuItem(name: 'item.two', route: 'route_two');

        $section->addItem($item1)
            ->addItem($divider)
            ->addItem($item2);

        $items = $section->getItems();
        $this->assertCount(3, $items);
        $this->assertSame($item1, $items[0]);
        $this->assertSame($divider, $items[1]);
        $this->assertTrue($items[1]->divider);
        $this->assertSame($item2, $items[2]);
    }

    public function testAddItemIsFluid(): void
    {
        $section = new MenuSection(name: 'title', position: 0);
        $item = new MenuItem(name: 'item', route: 'route');

        $result = $section->addItem($item);

        $this->assertSame($section, $result);
    }
}
