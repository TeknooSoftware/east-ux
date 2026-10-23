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

namespace Teknoo\East\UX\Service;

use Symfony\Contracts\Service\ResetInterface;
use Teknoo\East\Common\Contracts\User\UserInterface;
use Teknoo\East\UX\Contracts\Enum\Menu\Location;
use Teknoo\East\UX\Contracts\Enum\Menu\Sorting;
use Teknoo\East\UX\Contracts\Menu\MenuGeneratorInterface;
use Teknoo\East\UX\Object\DTO\Menu\MenuSection;

use function array_values;
use function count;
use function usort;

/**
 * Menu service.
 * Menus are built by the generators (tagged `teknoo.east.ux.menu.generator`) and cached in the instance per user
 * and location until `reset()` is called (tag `kernel.reset`, between two requests in a worker or two tests).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class MenuService implements ResetInterface
{
    /**
     * @var array<string, array<string, array<int, MenuSection>>>
     */
    private array $cache = [];

    /**
     * @var array<string, array<string, MenuSection>>
     */
    private array $currentSections = [];

    /**
     * @param iterable<MenuGeneratorInterface> $generators
     * @phpstan-param iterable<MenuGeneratorInterface> $generators
     */
    public function __construct(
        private readonly iterable $generators,
    ) {
    }

    /**
     * @return array<int, MenuSection>
     */
    public function getMenuFor(UserInterface $user, Location $location): array
    {
        $userId = $user->getUserIdentifier();

        if (isset($this->cache[$userId][$location->value])) {
            return $this->cache[$userId][$location->value];
        }

        $clone = clone $this;
        $clone->currentSections = [];
        $roles = array_values([...$user->getRoles()]);

        foreach ($this->generators as $generator) {
            $generator->generate($clone, $location, $user, $roles);
        }

        $sections = $clone->currentSections[$location->value] ?? [];
        usort(
            $sections,
            static fn(MenuSection $a, MenuSection $b): int => $a->position <=> $b->position,
        );

        $this->cache[$userId][$location->value] = $sections;

        return $this->cache[$userId][$location->value];
    }

    public function getSection(
        string $name,
        Location $location,
        string $icon = '',
        Sorting $position = Sorting::Append,
        bool $rawLabel = false,
        ?string $translationDomain = null,
    ): MenuSection {
        if (!isset($this->currentSections[$location->value][$name])) {
            $sectionPosition = match ($position) {
                Sorting::Append  => count($this->currentSections[$location->value] ?? []) + 1,
                Sorting::Prepend => 0,
            };

            $this->currentSections[$location->value][$name] = new MenuSection(
                name: $name,
                position: $sectionPosition,
                icon: $icon,
                rawLabel: $rawLabel,
                translationDomain: $translationDomain,
            );
        }

        return $this->currentSections[$location->value][$name];
    }

    public function reset(): void
    {
        $this->cache = [];
        $this->currentSections = [];
    }
}
