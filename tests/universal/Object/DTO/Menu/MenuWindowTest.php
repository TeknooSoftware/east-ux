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
use Teknoo\East\UX\Object\DTO\Menu\MenuWindow;

/**
 * Data transfer object for the window opened in the page by a menu item.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(MenuWindow::class)]
class MenuWindowTest extends TestCase
{
    public function testConstruct(): void
    {
        $window = new MenuWindow(
            component: 'ListWindow',
            title: 'app.item.title.list',
            props: ['windowId' => 'items', 'floating' => true, 'tableHeaders' => ['app.item.name']],
        );

        $this->assertInstanceOf(MenuWindow::class, $window);
        $this->assertSame('ListWindow', $window->component);
        $this->assertSame('app.item.title.list', $window->title);
        $this->assertSame(
            ['windowId' => 'items', 'floating' => true, 'tableHeaders' => ['app.item.name']],
            $window->props,
        );
    }

    public function testDefaultProps(): void
    {
        $window = new MenuWindow(
            component: 'FormWindow',
            title: 'app.menu.settings',
        );

        $this->assertSame('FormWindow', $window->component);
        $this->assertSame('app.menu.settings', $window->title);
        $this->assertSame([], $window->props);
    }
}
