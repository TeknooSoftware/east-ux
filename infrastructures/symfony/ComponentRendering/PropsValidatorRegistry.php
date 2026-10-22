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

namespace Teknoo\East\UXBundle\ComponentRendering;

use Teknoo\East\UXBundle\ComponentRendering\Exception\UnreferencedComponentException;
use Teknoo\East\UXBundle\Contracts\ComponentRendering\PropsValidatorInterface;
use Teknoo\Recipe\Promise\PromiseInterface;

use function sprintf;

/**
 * Registry of the validators of the props of the components rendered by the component renderer, indexed by the class
 * of their component. The validators are the services tagged `teknoo.east.ux.component_rendering.validation`, the
 * attribute `component` of the tag giving the class of the component: a component is referenced only through its
 * validator.
 *
 * Without validator for the component, the registry ignores the props and calls `success()` when
 * `allowUnreferencedComponents` is true (option `teknoo_east_ux.component_rendering.allow_unreferenced_components`),
 * else it calls `fail()` with an `UnreferencedComponentException`.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class PropsValidatorRegistry
{
    /**
     * @var array<string, PropsValidatorInterface>
     */
    private array $validators = [];

    /**
     * @param iterable<string, PropsValidatorInterface> $validators
     */
    public function __construct(
        iterable $validators,
        private readonly bool $allowUnreferencedComponents = false,
    ) {
        foreach ($validators as $componentClass => $validator) {
            $this->validators[$componentClass] = $validator;
        }
    }

    /**
     * @param array<mixed> $props
     * @param PromiseInterface<array<mixed>, mixed> $promise
     */
    public function validate(string $component, array $props, PromiseInterface $promise): self
    {
        if (isset($this->validators[$component])) {
            $this->validators[$component]->validate($props, $promise);

            return $this;
        }

        if ($this->allowUnreferencedComponents) {
            $promise->success($props);

            return $this;
        }

        $promise->fail(
            new UnreferencedComponentException(
                sprintf('The component %s can not be rendered: it has no validator of its props', $component)
            )
        );

        return $this;
    }
}
