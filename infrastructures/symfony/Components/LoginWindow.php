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

use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Live Twig component rendering the login window interface.
 *
 * The form follows Symfony's `form_login` (`_username`, `_password`, `_remember_me` when `rememberMe` is true, and
 * `_csrf_token` generated for the token id `csrfTokenId` when it is not empty). The last authentication error is
 * displayed, translated from its message key and data in the `security` domain, in an alert with the id
 * `login-error`.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsTwigComponent]
final class LoginWindow extends AbstractWindow
{
    public string $actionPath = '';

    public bool $rememberMe = true;

    public string $csrfTokenId = 'authenticate';

    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils,
    ) {
    }

    public function getAuthenticationError(): mixed
    {
        return $this->authenticationUtils->getLastAuthenticationError();
    }

    public function getLastUsername(): string
    {
        return $this->authenticationUtils->getLastUsername();
    }
}
