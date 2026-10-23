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

namespace Teknoo\East\UXBundle\Theme;

use DomainException;
use Symfony\Component\HttpFoundation\RequestStack;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Contracts\Theme\ThemePreferenceStorageInterface;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\Recipe\Promise\PromiseInterface;

use function is_array;
use function is_string;

/**
 * Storage of the theme preference of the visitor in the Symfony session, under the key `teknoo_east_ux_theme`.
 * The session is read only when the request comes with a session started by a previous request: rendering a page for
 * a visitor without session does not start one. A missing or invalid value is reported as a failure, the default
 * preference is then used.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class SessionThemePreferenceStorage implements ThemePreferenceStorageInterface
{
    public const string SESSION_KEY = 'teknoo_east_ux_theme';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function load(PromiseInterface $promise): ThemePreferenceStorageInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasPreviousSession()) {
            $promise->fail(new DomainException('No session holds a theme preference', 404));

            return $this;
        }

        $value = $request->getSession()->get(self::SESSION_KEY);
        $style = is_array($value) && is_string($value['style'] ?? null) ? Style::tryFrom($value['style']) : null;
        $mode = is_array($value) && is_string($value['mode'] ?? null) ? Mode::tryFrom($value['mode']) : null;

        if (null === $style || null === $mode) {
            $promise->fail(new DomainException('No valid theme preference in the session', 404));

            return $this;
        }

        $promise->success(new ThemePreference($style, $mode));

        return $this;
    }

    public function save(ThemePreference $preference): ThemePreferenceStorageInterface
    {
        $this->requestStack->getSession()->set(
            self::SESSION_KEY,
            [
                'style' => $preference->style->value,
                'mode' => $preference->mode->value,
            ],
        );

        return $this;
    }
}
