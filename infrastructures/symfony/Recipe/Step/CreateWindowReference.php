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

namespace Teknoo\East\UXBundle\Recipe\Step;

use DomainException;
use ReflectionClass;
use ReflectionMethod;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Teknoo\East\Common\View\ParametersBag;
use Teknoo\East\Foundation\Manager\ManagerInterface;
use Teknoo\East\UX\Contracts\Recipe\Step\CreateWindowReferenceInterface;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;
use Teknoo\East\UXBundle\Components\AbstractWindow;

use function is_a;

/**
 * Creates the reference of a Live window. A class which is not a Live window is refused before calling any of its
 * methods, and a callable method of the reference must be a Live action: the browser could not call it otherwise, and
 * the window would only be rendered again. The messages of these errors never repeat the name given to the step.
 *
 * A new reference is created at each run of the recipe, also for a re-render of the window: the window keeps its first
 * one, so the reference of the workplan is only useful in the run displaying the window.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
final class CreateWindowReference implements CreateWindowReferenceInterface
{
    public function __invoke(
        ManagerInterface $manager,
        ParametersBag $parametersBag,
        string $windowClass,
        string $workPlanKey = WindowReference::class,
        string $viewKey = 'windowReference',
    ): CreateWindowReferenceInterface {
        if (!is_a($windowClass, AbstractWindow::class, true)) {
            $manager->error(new DomainException('Only a window can have a reference', 400));

            return $this;
        }

        if (empty((new ReflectionClass($windowClass))->getAttributes(AsLiveComponent::class))) {
            $manager->error(new DomainException('Only a Live window can have a reference', 400));

            return $this;
        }

        $reference = $windowClass::createReference();

        foreach ($reference->methods as $method) {
            if (empty((new ReflectionMethod($windowClass, $method))->getAttributes(LiveAction::class))) {
                $manager->error(new DomainException('The methods of a reference must be Live actions', 400));

                return $this;
            }
        }

        $parametersBag->set($viewKey, $reference);
        $manager->updateWorkPlan([$workPlanKey => $reference]);

        return $this;
    }
}
