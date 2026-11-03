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

use InvalidArgumentException;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\TwigComponent\ComponentFactory;
use Teknoo\East\CommonBundle\Contracts\Rendering\ComponentFactoryInterface;
use Teknoo\East\UXBundle\ComponentRendering\Exception\InvalidPropsException;
use Teknoo\East\UXBundle\Contracts\ComponentRendering\PropsValidatorInterface;
use Teknoo\Recipe\Promise\PromiseInterface;

use function count;
use function is_a;
use function is_string;

/**
 * Validator of the props of the `ListWindow` component sent to the component renderer, a floating list window opened
 * over the page: `windowId`, `originalPath` (the path of the route whose recipe loads the list at the re-renders),
 * `itemComponent` (a component known by Symfony UX) and `floating` (always true) are required. The window props
 * (`title`, `closable`, `resizable`, `draggable`, `closeAriaLabel`, `resizeAriaLabel`), `hidden`, `tableHeaders` (an
 * array of strings), `searchFormName` and `newFormName` (form types), `newFormRoute` (a route generable without
 * parameters, as the template calls `path(newFormRoute)`), `newButtonLabel`, `newFormTitle` and `page` (a positive
 * integer) are optional, any other prop (`closeRedirectUrl`, `objectsCollection`, `pageCount`, `formView`, ...) is
 * refused. Only `searchFormName`, whose property is nullable, may be `null`.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
final class ListWindowPropsValidator implements PropsValidatorInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ComponentFactory|ComponentFactoryInterface $componentFactory,
    ) {
    }

    public function validate(array $props, PromiseInterface $promise): PropsValidatorInterface
    {
        $isFormType = static function (mixed $value, ExecutionContextInterface $context): void {
            if (is_string($value) && !is_a($value, FormTypeInterface::class, true)) {
                $context->addViolation('This value is not a form type.');
            }
        };

        // Same lookup as the component renderer, anonymous components included
        $componentFactory = $this->componentFactory;
        $isKnownComponent = static function (
            mixed $value,
            ExecutionContextInterface $context,
        ) use ($componentFactory): void {
            if (!is_string($value)) {
                return;
            }

            try {
                $componentFactory->metadataFor($value);
            } catch (InvalidArgumentException) {
                $context->addViolation('This value is not a known component.');
            }
        };

        // The template of ListWindow calls `path(newFormRoute)`, without parameters
        $urlGenerator = $this->urlGenerator;
        $isRouteWithoutParameters = static function (
            mixed $value,
            ExecutionContextInterface $context,
        ) use ($urlGenerator): void {
            if (!is_string($value)) {
                return;
            }

            try {
                $urlGenerator->generate($value);
            } catch (RoutingExceptionInterface) {
                $context->addViolation('This value is not a route without parameters.');
            }
        };

        $violations = $this->validator->validate(
            $props,
            new Assert\Collection(
                fields: [
                    'windowId' => [new Assert\Type('string'), new Assert\NotBlank()],
                    'originalPath' => [new Assert\Type('string'), new Assert\NotBlank()],
                    'itemComponent' => [
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                        new Assert\Callback($isKnownComponent),
                    ],
                    'floating' => [new Assert\NotNull(), new Assert\Type('bool'), new Assert\IsTrue()],
                    'hidden' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('bool')]),
                    'closable' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('bool')]),
                    'resizable' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('bool')]),
                    'draggable' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('bool')]),
                    'title' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('string')]),
                    'closeAriaLabel' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('string')]),
                    'resizeAriaLabel' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('string')]),
                    'tableHeaders' => new Assert\Optional(
                        [
                            new Assert\NotNull(),
                            new Assert\Type('array'),
                            new Assert\All([new Assert\NotNull(), new Assert\Type('string')]),
                        ]
                    ),
                    'searchFormName' => new Assert\Optional(
                        [new Assert\Type('string'), new Assert\Callback($isFormType)]
                    ),
                    'newFormName' => new Assert\Optional(
                        [new Assert\NotNull(), new Assert\Type('string'), new Assert\Callback($isFormType)]
                    ),
                    'newFormRoute' => new Assert\Optional(
                        [
                            new Assert\Type('string'),
                            new Assert\NotBlank(),
                            new Assert\Callback($isRouteWithoutParameters),
                        ]
                    ),
                    'newButtonLabel' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('string')]),
                    'newFormTitle' => new Assert\Optional([new Assert\NotNull(), new Assert\Type('string')]),
                    'page' => new Assert\Optional(
                        [new Assert\NotNull(), new Assert\Type('int'), new Assert\Positive()]
                    ),
                ],
                allowExtraFields: false,
                allowMissingFields: false,
            ),
        );

        if (0 < count($violations)) {
            $promise->fail(
                new InvalidPropsException('Invalid props for the component ListWindow')
            );

            return $this;
        }

        $promise->success($props);

        return $this;
    }
}
