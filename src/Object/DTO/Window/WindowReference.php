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

namespace Teknoo\East\UX\Object\DTO\Window;

use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;

use function class_exists;
use function method_exists;
use function sprintf;

/**
 * Data transfer object naming a window opened in a browser, to call its methods from outside of the page (a worker,
 * another tab, ...). `componentClass` is the class of the component, `id` identifies this window among all others, and
 * `methods` lists the methods that can be called: public, without parameter and returning nothing, since only the name
 * of a method is sent to the window. Any other method is refused.
 *
 * The reference holds no business data. It is serializable, a task can carry it to the worker.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
final readonly class WindowReference
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public string $componentClass,
        public string $id,
        public array $methods,
    ) {
        if (!class_exists($this->componentClass)) {
            throw new InvalidArgumentException(
                message: sprintf('The window class "%s" does not exist', $this->componentClass),
                code: 500,
            );
        }

        foreach ($this->methods as $method) {
            if (!method_exists($this->componentClass, $method)) {
                throw new InvalidArgumentException(
                    message: sprintf('The window "%s" has no method "%s"', $this->componentClass, $method),
                    code: 500,
                );
            }

            $reflection = new ReflectionMethod($this->componentClass, $method);

            if (!$reflection->isPublic()) {
                throw new InvalidArgumentException(
                    message: sprintf('The method "%s" of "%s" must be public', $method, $this->componentClass),
                    code: 500,
                );
            }

            if (0 < $reflection->getNumberOfParameters()) {
                throw new InvalidArgumentException(
                    message: sprintf('The method "%s" of "%s" must have no parameter', $method, $this->componentClass),
                    code: 500,
                );
            }

            $returnType = $reflection->getReturnType();
            if (!$returnType instanceof ReflectionNamedType || 'void' !== $returnType->getName()) {
                throw new InvalidArgumentException(
                    message: sprintf('The method "%s" of "%s" must return void', $method, $this->componentClass),
                    code: 500,
                );
            }
        }
    }
}
