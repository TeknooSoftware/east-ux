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

namespace Teknoo\Tests\East\UX\Support\Fixtures\Recipe\Plan;

use Teknoo\East\Common\Recipe\Step\RenderError;
use Teknoo\East\UX\Contracts\Recipe\Step\CreateWindowReferenceInterface;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;
use Teknoo\Recipe\BaseRecipeInterface;
use Teknoo\Recipe\Bowl\Bowl;
use Teknoo\Recipe\EditablePlanInterface;
use Teknoo\Recipe\Plan\EditablePlanTrait;
use Teknoo\Recipe\RecipeInterface;
use Teknoo\Recipe\Value;

/**
 * Plan of the test application for a page whose window has a reference: it creates the reference, then runs an East
 * Common endpoint, as an application does. With `secondViewKey`, it creates the reference of a second window of the
 * page.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ReferencedWindowEndPoint implements EditablePlanInterface
{
    use EditablePlanTrait;

    public function __construct(
        RecipeInterface $recipe,
        private readonly CreateWindowReferenceInterface $createWindowReference,
        private readonly BaseRecipeInterface $endPoint,
        private readonly RenderError $renderError,
        private readonly ?string $secondViewKey = null,
    ) {
        $this->fill($recipe);
    }

    protected function populateRecipe(RecipeInterface $recipe): RecipeInterface
    {
        $recipe = $recipe->cook(
            $this->createWindowReference,
            CreateWindowReferenceInterface::class,
            ['viewKey' => new Value('windowReference'), 'workPlanKey' => new Value(WindowReference::class)],
            10,
        );

        if (null !== $this->secondViewKey) {
            $recipe = $recipe->cook(
                $this->createWindowReference,
                CreateWindowReferenceInterface::class . ':second',
                ['viewKey' => new Value($this->secondViewKey), 'workPlanKey' => new Value($this->secondViewKey)],
                11,
            );
        }

        $recipe = $recipe->execute($this->endPoint, 'endPoint', 1, 20);

        return $recipe->onError(new Bowl($this->renderError, []));
    }
}
