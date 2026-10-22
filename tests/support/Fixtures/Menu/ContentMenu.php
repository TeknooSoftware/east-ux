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

namespace Teknoo\Tests\East\UX\Support\Fixtures\Menu;

use Teknoo\East\Common\Contracts\User\UserInterface;
use Teknoo\East\UX\Contracts\Menu\MenuGeneratorInterface;
use Teknoo\East\UX\Contracts\Enum\Menu\Location;
use Teknoo\East\UX\Object\DTO\Menu\MenuItem;
use Teknoo\East\UX\Service\MenuService;

use function in_array;

/**
 * Menu generator of the test application for `Location::TopLeft`: a "content" section for every user and an
 * "administration" section for the users granted `ROLE_ADMIN`. Labels are translation keys of the application
 * (default domain).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ContentMenu implements MenuGeneratorInterface
{
    public function generate(MenuService $menuService, Location $location, UserInterface $user, array $roles): void
    {
        if (Location::TopLeft !== $location) {
            return;
        }

        $menuService->getSection('fixtures.menu.content', $location, 'icon-content')
            ->addItem(new MenuItem(name: 'fixtures.menu.items', route: 'fixtures_item_list', icon: 'icon-list'))
            ->addItem(new MenuItem(name: 'fixtures.menu.new_item', route: 'fixtures_item_new'));

        if (in_array('ROLE_ADMIN', $roles, true)) {
            $menuService->getSection('fixtures.menu.administration', $location)
                ->addItem(new MenuItem(name: 'fixtures.menu.admin_new_item', route: 'fixtures_admin_item_new'));
        }
    }
}
