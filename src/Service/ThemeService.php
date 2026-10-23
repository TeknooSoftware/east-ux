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
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Contracts\Theme\ThemePreferenceStorageInterface;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\Recipe\Promise\Promise;

/**
 * Theme service: the theme preference of the current visitor, read from the storage (or the default preference when
 * nothing is stored, or without storage, when the preference is kept by the browser) and cached in the instance until
 * `reset()` is called (tag `kernel.reset`), and the rendering of a preference on `<html>`:
 *
 * | Style  | Mode  | Class           | `data-bs-theme` |
 * |--------|-------|-----------------|-----------------|
 * | Legacy | Light | none            | none            |
 * | Modern | Light | `system-modern` | `light`         |
 * | any    | Dark  | by the style    | `dark`          |
 * | any    | Auto  | by the style    | as `Light`, resolved by the browser from `prefers-color-scheme` |
 *
 * The same rules are applied by the `theme` Stimulus controller and the script of `east_ux_theme_script()`.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ThemeService implements ResetInterface
{
    public const string MODERN_CLASS = 'system-modern';

    private ?ThemePreference $preference = null;

    public function __construct(
        private readonly ThemePreference $default = new ThemePreference(),
        private readonly ?ThemePreferenceStorageInterface $storage = null,
    ) {
    }

    public function getDefault(): ThemePreference
    {
        return $this->default;
    }

    public function getPreference(): ThemePreference
    {
        if (null !== $this->preference) {
            return $this->preference;
        }

        $preference = $this->default;

        /** @var Promise<ThemePreference, mixed, mixed> $promise */
        $promise = new Promise(
            static function (ThemePreference $stored) use (&$preference): void {
                $preference = $stored;
            },
            static function (): void {
                // Nothing stored: the default preference is kept
            },
        );

        $this->storage?->load($promise);

        return $this->preference = $preference;
    }

    public function update(ThemePreference $preference): self
    {
        $this->storage?->save($preference);
        $this->preference = $preference;

        return $this;
    }

    public function getHtmlClass(ThemePreference $preference): string
    {
        return match ($preference->style) {
            Style::Modern => self::MODERN_CLASS,
            Style::Legacy => '',
        };
    }

    public function getBootstrapTheme(ThemePreference $preference): ?string
    {
        return match (true) {
            Mode::Dark === $preference->mode => 'dark',
            Style::Modern === $preference->style => 'light',
            default => null,
        };
    }

    public function reset(): void
    {
        $this->preference = null;
    }
}
