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

namespace Teknoo\East\UXBundle\ComponentRendering\Validator;

use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Teknoo\East\UXBundle\ComponentRendering\Exception\InvalidPropsException;
use Teknoo\East\UXBundle\Contracts\ComponentRendering\PropsValidatorInterface;
use Teknoo\Recipe\Promise\PromiseInterface;

use function count;
use function is_a;
use function is_string;

/**
 * Validator of the props of the `FormWindow` component sent to the component renderer by the
 * `form-window-dynamic-loader` Stimulus controller: `windowId`, `formPath` and `formName` (a form type) are required,
 * `originalPath`, `objectId`, `title`, `closeAriaLabel`, `closable`, `draggable` and `hidden` are optional, any other
 * prop is refused.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class FormWindowPropsValidator implements PropsValidatorInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validate(array $props, PromiseInterface $promise): PropsValidatorInterface
    {
        $violations = $this->validator->validate(
            $props,
            new Assert\Collection(
                fields: [
                    'windowId' => [new Assert\Type('string'), new Assert\NotBlank()],
                    'formPath' => [new Assert\Type('string'), new Assert\NotBlank()],
                    'formName' => [
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                        new Assert\Callback(
                            static function (mixed $value, ExecutionContextInterface $context): void {
                                if (is_string($value) && !is_a($value, FormTypeInterface::class, true)) {
                                    $context->addViolation('This value is not a form type.');
                                }
                            }
                        ),
                    ],
                    'originalPath' => new Assert\Optional(new Assert\Type('string')),
                    'objectId' => new Assert\Optional(new Assert\Type('string')),
                    'title' => new Assert\Optional(new Assert\Type('string')),
                    'closeAriaLabel' => new Assert\Optional(new Assert\Type('string')),
                    'closable' => new Assert\Optional(new Assert\Type('bool')),
                    'draggable' => new Assert\Optional(new Assert\Type('bool')),
                    'hidden' => new Assert\Optional(new Assert\Type('bool')),
                ],
                allowExtraFields: false,
                allowMissingFields: false,
            ),
        );

        if (0 < count($violations)) {
            $promise->fail(
                new InvalidPropsException('Invalid props for the component FormWindow')
            );

            return $this;
        }

        $promise->success($props);

        return $this;
    }
}
