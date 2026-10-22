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
use Teknoo\East\UX\Contracts\Enum\Menu\Sorting;

/**
 * Menu generator of the test application, as an application builds its user dropdown: on `Location::TopRight`, a
 * section titled with the display name of the user (a raw label, not a translation key) with the settings entry, a
 * divider and the logout entry; on `Location::TopLeft`, a "home" section prepended before the sections of the other
 * generators.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class UserMenu implements MenuGeneratorInterface
{
    public function generate(MenuService $menuService, Location $location, UserInterface $user, array $roles): void
    {
        if (Location::TopLeft === $location) {
            $menuService->getSection('fixtures.menu.home', $location, position: Sorting::Prepend)
                ->addItem(new MenuItem(name: 'fixtures.menu.dashboard', route: 'fixtures_home'));

            return;
        }

        $menuService->getSection((string) $user, $location, 'icon-user', rawLabel: true)
            ->addItem(
                new MenuItem(
                    name: 'fixtures.menu.settings',
                    route: 'fixtures_home',
                    routeParameters: ['page' => 'settings'],
                    icon: 'icon-settings',
                )
            )
            ->addItem(new MenuItem(name: '', route: '', divider: true))
            ->addItem(new MenuItem(name: $user->getUserIdentifier(), route: 'fixtures_home', rawLabel: true))
            ->addItem(
                new MenuItem(
                    name: 'fixtures.menu.logout',
                    route: 'fixtures_home',
                    routeParameters: ['page' => 'logout'],
                )
            );
    }
}
