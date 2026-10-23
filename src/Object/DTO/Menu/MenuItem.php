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

/**
 * Data transfer object for menu item data.
 * `divider` marks a separator between two entries (its name and route are not rendered), `rawLabel` a name
 * displayed as is instead of being translated as a translation key (the name is still escaped).
 * An item without route (and not a divider) is rendered as a button, for an entry handled by a Stimulus controller.
 * `attributes` are HTML attributes added to the link or the button (escaped), for example `data-action`, and
 * `translationDomain` the domain of the name (the default domain of the application when it is null).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
readonly class MenuItem
{
    /**
     * @param array<string, mixed> $routeParameters
     * @param array<string, string> $attributes
     */
    public function __construct(
        public string $name,
        public string $route,
        public array $routeParameters = [],
        public string $icon = '',
        public bool $divider = false,
        public bool $rawLabel = false,
        public array $attributes = [],
        public ?string $translationDomain = null,
    ) {
    }
}
