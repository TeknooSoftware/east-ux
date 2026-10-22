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

namespace Teknoo\East\UXBundle\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Teknoo\East\Common\Contracts\User\UserInterface;
use Teknoo\East\UX\Contracts\Enum\Menu\Location;
use Teknoo\East\UX\Object\DTO\Menu\MenuSection;
use Teknoo\East\UX\Service\MenuService;

/**
 * Twig component rendering the menu bar: the brand (block `brand`, linked to the route `home_route` of the bundle
 * configuration, or to `/`), the `Location::TopLeft` sections on the left and the `Location::TopRight` sections
 * on the right, all built by `MenuService` from the menu generators of the application for the `user` prop.
 * Without user, no section is rendered.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsTwigComponent]
final class MenuBar
{
    public ?UserInterface $user = null;

    public function __construct(
        private readonly MenuService $menuService,
        private readonly ?string $homeRoute = null,
    ) {
    }

    public function getHomeRoute(): ?string
    {
        return $this->homeRoute;
    }

    /**
     * @return array<int, MenuSection>
     */
    private function getItems(Location $location): array
    {
        if (null === $this->user) {
            return [];
        }

        return $this->menuService->getMenuFor($this->user, $location);
    }

    /**
     * @return array<int, MenuSection>
     */
    public function getLeftItems(): array
    {
        return $this->getItems(Location::TopLeft);
    }

    /**
     * @return array<int, MenuSection>
     */
    public function getRightItems(): array
    {
        return $this->getItems(Location::TopRight);
    }
}
