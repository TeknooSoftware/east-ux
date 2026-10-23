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

namespace Teknoo\East\UX\Contracts\Theme;

use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\Recipe\Promise\PromiseInterface;

/**
 * Storage of the theme preference of the current visitor, used by `ThemeService`. East UX provides a storage in the
 * session (the bundle's `SessionThemePreferenceStorage`); an application can provide its own, for example reading
 * and writing the preference in the account of the authenticated user.
 *
 * `load()` calls `success()` with the stored preference, or `fail()` when no preference is stored (the default
 * preference of the configuration is then used).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
interface ThemePreferenceStorageInterface
{
    /**
     * @param PromiseInterface<ThemePreference, mixed> $promise
     */
    public function load(PromiseInterface $promise): ThemePreferenceStorageInterface;

    public function save(ThemePreference $preference): ThemePreferenceStorageInterface;
}
