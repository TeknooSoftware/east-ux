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

namespace Teknoo\East\UXBundle\Contracts\ComponentRendering;

use Teknoo\Recipe\Promise\PromiseInterface;

/**
 * Contract of the validators of the props of a component rendered by the component renderer. A validator supports one
 * component: it is registered with the tag `teknoo.east.ux.component_rendering.validation`, whose attribute
 * `component` is the class of this component. It calls `success()` of the promise with the props when they are valid,
 * or `fail()` with an `InvalidPropsException`.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
interface PropsValidatorInterface
{
    /**
     * @param array<mixed> $props
     * @param PromiseInterface<array<mixed>, mixed> $promise
     */
    public function validate(array $props, PromiseInterface $promise): PropsValidatorInterface;
}
