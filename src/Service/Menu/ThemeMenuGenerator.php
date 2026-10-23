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

namespace Teknoo\East\UX\Service\Menu;

use Teknoo\East\Common\Contracts\User\UserInterface;
use Teknoo\East\UX\Contracts\Enum\Menu\Location;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Contracts\Menu\MenuGeneratorInterface;
use Teknoo\East\UX\Object\DTO\Menu\MenuItem;
use Teknoo\East\UX\Object\DTO\Menu\MenuSection;
use Teknoo\East\UX\Service\MenuService;

/**
 * Menu generator adding the theme switcher to the menu bar: the modes (light, dark, auto) and the styles (legacy,
 * modern) of the System.css x Bootstrap theme, as buttons handled by the `theme` Stimulus controller (set on `<html>`
 * by `east_ux_theme_attributes()`), which switches the theme without reloading the page. The entries are its
 * `menuOption` targets (`role="menuitemradio"`): the current ones are checked (`aria-checked`, a check mark drawn by
 * `east-ux.css`).
 *
 * Tagged `teknoo.east.ux.menu.generator`, it adds its own section on its location. An application can instead add
 * the entries to one of its sections, for example the dropdown of the user, with `addItemsTo()`.
 * The labels are keys of the `east_ux` domain.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ThemeMenuGenerator implements MenuGeneratorInterface
{
    public const string SECTION_NAME = 'teknoo.east.ux.theme_switcher.group.theme';

    public const string TRANSLATION_DOMAIN = 'east_ux';

    private const array MODES = [
        'teknoo.east.ux.theme_switcher.mode.light' => Mode::Light,
        'teknoo.east.ux.theme_switcher.mode.dark' => Mode::Dark,
        'teknoo.east.ux.theme_switcher.mode.auto' => Mode::Auto,
    ];

    private const array STYLES = [
        'teknoo.east.ux.theme_switcher.style.legacy' => Style::Legacy,
        'teknoo.east.ux.theme_switcher.style.modern' => Style::Modern,
    ];

    public function __construct(
        private readonly Location $location = Location::TopRight,
        private readonly bool $withMode = true,
        private readonly bool $withStyle = true,
        private readonly string $icon = '',
    ) {
    }

    public function generate(MenuService $menuService, Location $location, UserInterface $user, array $roles): void
    {
        if ($this->location !== $location || (!$this->withMode && !$this->withStyle)) {
            return;
        }

        $this->addItemsTo(
            $menuService->getSection(
                name: self::SECTION_NAME,
                location: $location,
                icon: $this->icon,
                translationDomain: self::TRANSLATION_DOMAIN,
            ),
        );
    }

    private function buildItem(string $name, string $parameter, string $value): MenuItem
    {
        return new MenuItem(
            name: $name,
            route: '',
            attributes: [
                'data-action' => 'click->theme#select',
                'data-theme-' . $parameter . '-param' => $value,
                'data-theme-target' => 'menuOption',
                'role' => 'menuitemradio',
                'aria-checked' => 'false',
            ],
            translationDomain: self::TRANSLATION_DOMAIN,
        );
    }

    public function addItemsTo(MenuSection $section): self
    {
        if ($this->withMode) {
            foreach (self::MODES as $name => $mode) {
                $section->addItem($this->buildItem($name, 'mode', $mode->value));
            }
        }

        if ($this->withMode && $this->withStyle) {
            $section->addItem(new MenuItem(name: '', route: '', divider: true));
        }

        if ($this->withStyle) {
            foreach (self::STYLES as $name => $style) {
                $section->addItem($this->buildItem($name, 'style', $style->value));
            }
        }

        return $this;
    }
}
