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

namespace Teknoo\Tests\East\UX\Support\Fixtures\config;

use Psr\Container\ContainerInterface;
use Teknoo\East\Common\Contracts\Recipe\Plan\EditObjectEndPointInterface;
use Teknoo\East\Common\Contracts\Recipe\Plan\ListObjectEndPointInterface;
use Teknoo\East\Common\Recipe\Step\RenderError;
use Teknoo\East\UX\Contracts\Recipe\Step\CreateWindowReferenceInterface;
use Teknoo\Recipe\RecipeInterface;
use Teknoo\Tests\East\UX\Support\Fixtures\Recipe\Plan\ReferencedWindowEndPoint;

/*
 * Plans of the test application using the reference of a window. An application defines its plans the same way, and
 * reads the step of East UX from the Symfony container.
 */
return [
    'fixtures.plan.item.followed' => static fn (ContainerInterface $container): ReferencedWindowEndPoint
        => new ReferencedWindowEndPoint(
            $container->get(RecipeInterface::class),
            $container->get(CreateWindowReferenceInterface::class),
            $container->get(ListObjectEndPointInterface::class),
            $container->get(RenderError::class),
        ),

    'fixtures.plan.item.followed_twice' => static fn (ContainerInterface $container): ReferencedWindowEndPoint
        => new ReferencedWindowEndPoint(
            $container->get(RecipeInterface::class),
            $container->get(CreateWindowReferenceInterface::class),
            $container->get(ListObjectEndPointInterface::class),
            $container->get(RenderError::class),
            'secondWindowReference',
        ),

    'fixtures.plan.item.followed_edit' => static fn (ContainerInterface $container): ReferencedWindowEndPoint
        => new ReferencedWindowEndPoint(
            $container->get(RecipeInterface::class),
            $container->get(CreateWindowReferenceInterface::class),
            $container->get(EditObjectEndPointInterface::class),
            $container->get(RenderError::class),
        ),
];
