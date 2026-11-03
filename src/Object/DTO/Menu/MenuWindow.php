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
 * Data transfer object for the window opened in the page by a menu item, instead of following its link.
 * `component` is the name of a component the component renderer accepts (`ListWindow`, `FormWindow`, ...), `title` a
 * translation key, translated in the domain of its item, and `props` the props of the component (JSON-serialisable,
 * an object and not a list). The window is used only on an item that has a route: the page of the route is the
 * fallback. The loader of the window adds `originalPath` (the path and the query of the link), `hidden` and the
 * translated `title` to the props.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
final readonly class MenuWindow
{
    /**
     * @param array<string, mixed> $props
     */
    public function __construct(
        public string $component,
        public string $title,
        public array $props = [],
    ) {
    }
}
