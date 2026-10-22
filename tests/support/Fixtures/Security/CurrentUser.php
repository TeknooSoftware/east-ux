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

namespace Teknoo\Tests\East\UX\Support\Fixtures\Security;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Teknoo\East\Common\Object\User;

use function ucfirst;

/**
 * Twig global of the test application (`fixtures_user`): the East Common user given to the `MenuBar` component,
 * built from the user authenticated by the firewall (in-memory Symfony users), or null for an anonymous request.
 * Its last name holds HTML special characters, to check that raw labels are escaped.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class CurrentUser
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        $securityUser = $token?->getUser();
        if (null === $token || null === $securityUser) {
            return null;
        }

        $identifier = $securityUser->getUserIdentifier();

        return (new User())
            ->setEmail($identifier . '@teknoo.test')
            ->setFirstName(ucfirst($identifier))
            ->setLastName('<Tester>')
            ->setRoles($token->getRoleNames());
    }
}
