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
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\East\UX\Service\ThemeService;

/**
 * Twig component rendering the switcher of the System.css x Bootstrap theme: a group of buttons for the modes (light,
 * dark, auto, shown when `showMode` is true) and a group for the styles (legacy, modern, shown when `showStyle` is
 * true), handled by the `theme` Stimulus controller set on `<html>` by `east_ux_theme_attributes()`. The button of the
 * current preference is pressed. Unlike the menu bar, it is rendered for anonymous visitors too.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsTwigComponent]
final class ThemeSwitcher
{
    public bool $showMode = true;

    public bool $showStyle = true;

    public function __construct(
        private readonly ThemeService $themeService,
    ) {
    }

    public function getPreference(): ThemePreference
    {
        return $this->themeService->getPreference();
    }
}
