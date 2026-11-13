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

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Teknoo\East\UXBundle\ComponentRendering\Exception\InvalidPropsException;
use Teknoo\East\UXBundle\Contracts\ComponentRendering\PropsValidatorInterface;
use Teknoo\Recipe\Promise\PromiseInterface;

use function count;

/**
 * Validator of the props of the `ObjectWindow` component sent to the component renderer of teknoo/east-ux, a floating
 * window opened over the page by a row of a list, on the model of `ListWindowPropsValidator`:
 *
 * - `windowId`, `originalPath` (the path of the route whose recipe loads the object at the re-renders),
 *   `contentComponent` and `floating` (always true) are required.
 * - `contentComponent` must be one of the components given to the constructor: accepting any known component would
 *   allow to nest a window in the window, and would end in an error of the server.
 * - The window props (`title`, `closable`, `resizable`, `draggable`, `closeAriaLabel`, `resizeAriaLabel`) and `hidden`
 *   are optional.
 * - Any other prop (`objectInstance`, `windowReference`, `closeRedirectUrl`, `@checksum`, ...) is refused, and no prop
 *   may be `null`: none of these properties is nullable.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
final class ObjectWindowPropsValidator implements PropsValidatorInterface
{
    /**
     * @param list<string> $contentComponents the components allowed as content of the window
     */
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly array $contentComponents,
    ) {
    }

    public function validate(array $props, PromiseInterface $promise): PropsValidatorInterface
    {
        $violations = $this->validator->validate(
            $props,
            new Assert\Collection(
                fields: [
                    'windowId' => [new Assert\Type('string'), new Assert\NotBlank()],
                    'originalPath' => [new Assert\Type('string'), new Assert\NotBlank()],
                    'contentComponent' => [
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                        new Assert\Choice(choices: $this->contentComponents),
                    ],
                    'floating' => [new Assert\NotNull(), new Assert\Type('bool'), new Assert\IsTrue()],
                    'hidden' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('bool')]),
                    'closable' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('bool')]),
                    'resizable' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('bool')]),
                    'draggable' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('bool')]),
                    'title' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('string')]),
                    'closeAriaLabel' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('string')]),
                    'resizeAriaLabel' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('string')]),
                ],
                allowExtraFields: false,
                allowMissingFields: false,
            ),
        );

        if (0 < count($violations)) {
            $promise->fail(
                new InvalidPropsException('Invalid props for the component ObjectWindow')
            );

            return $this;
        }

        $promise->success($props);

        return $this;
    }
}
