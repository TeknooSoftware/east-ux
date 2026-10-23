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

namespace Teknoo\East\UX\Object\DTO\Menu;

use Teknoo\East\UX\Contracts\Enum\Menu\Sorting;

use function array_unshift;

/**
 * Data transfer object for menu section data.
 * `rawLabel` marks a name displayed as is instead of being translated as a translation key (the name is still
 * escaped), for example a section titled with the user's display name, and `translationDomain` the domain of the name
 * (the default domain of the application when it is null).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class MenuSection
{
    /**
     * @var array<int, MenuItem>
     */
    private array $items = [];

    public function __construct(
        public readonly string $name,
        public readonly int $position,
        public readonly string $icon = '',
        public readonly bool $rawLabel = false,
        public readonly ?string $translationDomain = null,
    ) {
    }

    public function addItem(MenuItem $item, Sorting $position = Sorting::Append): self
    {
        if ($position === Sorting::Prepend) {
            array_unshift($this->items, $item);
        } else {
            $this->items[] = $item;
        }

        return $this;
    }

    /**
     * @return array<int, MenuItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
