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

namespace Teknoo\East\UX\Contracts\Recipe\Step;

use Teknoo\East\Common\View\ParametersBag;
use Teknoo\East\Foundation\Manager\ManagerInterface;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;

/**
 * Step creating the reference of a Live window from the class of its component, before its rendering. The reference is
 * added to the workplan under `workPlanKey`, for the next steps, and to the view under `viewKey`, for the template:
 * East Common's rendering steps read only the view. Two windows of a page need two values of `viewKey`, and two values
 * of `workPlanKey` when a next step reads the reference: the second run replaces the first one otherwise.
 *
 * A parameter left open can be set by the request, even with a default value here: `windowClass`, `viewKey` and
 * `workPlanKey` must come from a default of the route or from a `Value` of the plan. East Common's `LoadObject` and
 * `CreateObject` read `workPlanKey` too: in a plan running them, this key must be a `Value`.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
interface CreateWindowReferenceInterface
{
    public function __invoke(
        ManagerInterface $manager,
        ParametersBag $parametersBag,
        string $windowClass,
        string $workPlanKey = WindowReference::class,
        string $viewKey = 'windowReference',
    ): CreateWindowReferenceInterface;
}
