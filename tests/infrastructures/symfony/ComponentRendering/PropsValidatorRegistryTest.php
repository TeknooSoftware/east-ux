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

namespace Teknoo\Tests\East\UXBundle\ComponentRendering;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Teknoo\East\UXBundle\ComponentRendering\Exception\InvalidPropsException;
use Teknoo\East\UXBundle\ComponentRendering\Exception\UnreferencedComponentException;
use Teknoo\East\UXBundle\ComponentRendering\PropsValidatorRegistry;
use Teknoo\East\UXBundle\Components\Card;
use Teknoo\East\UXBundle\Components\FormWindow;
use Teknoo\East\UXBundle\Contracts\ComponentRendering\PropsValidatorInterface;
use Teknoo\Recipe\Promise\PromiseInterface;
use Throwable;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(PropsValidatorRegistry::class)]
#[CoversClass(InvalidPropsException::class)]
#[CoversClass(UnreferencedComponentException::class)]
class PropsValidatorRegistryTest extends TestCase
{
    /**
     * @param PromiseInterface<array<mixed>, mixed>|null $promise
     */
    private function buildRegistry(bool $allowUnreferencedComponents, ?PromiseInterface $promise): PropsValidatorRegistry
    {
        $validator = $this->createMock(PropsValidatorInterface::class);
        $validator->expects(null !== $promise ? $this->once() : $this->never())
            ->method('validate')
            ->with(['windowId' => 'foo'], $promise)
            ->willReturnSelf();

        return new PropsValidatorRegistry(
            new ArrayIterator([FormWindow::class => $validator]),
            $allowUnreferencedComponents,
        );
    }

    public function testValidateWithTheValidatorOfTheComponent(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $promise->expects($this->never())->method('success');
        $promise->expects($this->never())->method('fail');

        $registry = $this->buildRegistry(false, $promise);

        $this->assertSame($registry, $registry->validate(FormWindow::class, ['windowId' => 'foo'], $promise));
    }

    public function testIgnoreAnUnreferencedComponentWhenAllowed(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $promise->expects($this->once())->method('success')->with(['windowId' => 'foo'])->willReturnSelf();
        $promise->expects($this->never())->method('fail');

        $registry = $this->buildRegistry(true, null);

        $this->assertSame($registry, $registry->validate(Card::class, ['windowId' => 'foo'], $promise));
    }

    public function testFailOnAnUnreferencedComponent(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $promise->expects($this->never())->method('success');
        $promise->expects($this->once())
            ->method('fail')
            ->with($this->callback(
                static fn (Throwable $error): bool => $error instanceof UnreferencedComponentException
                    && 403 === $error->getStatusCode()
            ))
            ->willReturnSelf();

        $registry = $this->buildRegistry(false, null);

        $this->assertSame($registry, $registry->validate(Card::class, ['windowId' => 'foo'], $promise));
    }

    public function testInvalidPropsExceptionIsABadRequest(): void
    {
        $this->assertSame(400, new InvalidPropsException('foo')->getStatusCode());
    }
}
