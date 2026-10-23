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

/**
 * Data transfer object for menu item data.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(MenuItem::class)]
class MenuItemTest extends TestCase
{
    public function testConstruct(): void
    {
        $item = new MenuItem(
            name: 'my.translation.key',
            route: 'app_home',
            routeParameters: ['id' => 42],
        );

        $this->assertInstanceOf(MenuItem::class, $item);
        $this->assertSame('my.translation.key', $item->name);
        $this->assertSame('app_home', $item->route);
        $this->assertSame(['id' => 42], $item->routeParameters);
    }

    public function testDefaultRouteParameters(): void
    {
        $item = new MenuItem(
            name: 'key',
            route: 'app_route',
        );

        $this->assertSame([], $item->routeParameters);
    }

    public function testDefaultFlags(): void
    {
        $item = new MenuItem(
            name: 'key',
            route: 'app_route',
        );

        $this->assertSame('', $item->icon);
        $this->assertFalse($item->divider);
        $this->assertFalse($item->rawLabel);
        $this->assertSame([], $item->attributes);
        $this->assertNull($item->translationDomain);
    }

    public function testIcon(): void
    {
        $item = new MenuItem(
            name: 'key',
            route: 'app_route',
            icon: 'icon-user',
        );

        $this->assertSame('icon-user', $item->icon);
    }

    public function testDivider(): void
    {
        $item = new MenuItem(
            name: '',
            route: '',
            divider: true,
        );

        $this->assertTrue($item->divider);
        $this->assertFalse($item->rawLabel);
    }

    public function testRawLabel(): void
    {
        $item = new MenuItem(
            name: 'John Doe',
            route: 'app_profile',
            rawLabel: true,
        );

        $this->assertSame('John Doe', $item->name);
        $this->assertTrue($item->rawLabel);
        $this->assertFalse($item->divider);
    }

    public function testAttributesAndTranslationDomain(): void
    {
        $item = new MenuItem(
            name: 'app.menu.night',
            route: '',
            attributes: ['data-action' => 'click->theme#select', 'data-theme-mode-param' => 'dark'],
            translationDomain: 'app_domain',
        );

        $this->assertSame('', $item->route);
        $this->assertSame(
            ['data-action' => 'click->theme#select', 'data-theme-mode-param' => 'dark'],
            $item->attributes,
        );
        $this->assertSame('app_domain', $item->translationDomain);
        $this->assertFalse($item->divider);
    }
}
