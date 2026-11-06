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

namespace Teknoo\Tests\East\UXBundle\Recipe\Step;

use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Teknoo\East\Common\View\ParametersBag;
use Teknoo\East\Foundation\Manager\ManagerInterface;
use Teknoo\East\UX\Contracts\Recipe\Step\CreateWindowReferenceInterface;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;
use Teknoo\East\UXBundle\Components\AbstractWindow;
use Teknoo\East\UXBundle\Components\FormWindow;
use Teknoo\East\UXBundle\Components\ListWindow;
use Teknoo\East\UXBundle\Components\LoginWindow;
use Teknoo\East\UXBundle\Components\Window;
use Teknoo\East\UXBundle\Recipe\Step\CreateWindowReference;
use Throwable;

use function array_keys;

/**
 * Step creating the reference of a Live window.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(CreateWindowReference::class)]
class CreateWindowReferenceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function readView(ParametersBag $parametersBag): array
    {
        $view = $parametersBag->transform();
        $this->assertIsArray($view);

        /** @var array<string, mixed> $view */
        return $view;
    }

    public function testInvokeGivesTheReferenceToTheWorkplanAndToTheView(): void
    {
        $workPlan = [];
        $manager = $this->createMock(ManagerInterface::class);
        $manager->expects($this->never())->method('error');
        $manager->expects($this->once())
            ->method('updateWorkPlan')
            ->willReturnCallback(
                static function (array $values) use (&$workPlan, $manager): ManagerInterface {
                    $workPlan = $values;

                    return $manager;
                },
            );

        $parametersBag = new ParametersBag();

        $this->assertInstanceOf(
            CreateWindowReferenceInterface::class,
            (new CreateWindowReference())($manager, $parametersBag, ListWindow::class),
        );

        $this->assertInstanceOf(WindowReference::class, $workPlan[WindowReference::class]);
        $this->assertSame(ListWindow::class, $workPlan[WindowReference::class]->componentClass);
        $this->assertSame(['windowReference' => $workPlan[WindowReference::class]], $this->readView($parametersBag));
    }

    public function testInvokeWithItsOwnKeys(): void
    {
        $workPlan = [];
        $manager = $this->createMock(ManagerInterface::class);
        $manager->expects($this->never())->method('error');
        $manager->expects($this->once())
            ->method('updateWorkPlan')
            ->willReturnCallback(
                static function (array $values) use (&$workPlan, $manager): ManagerInterface {
                    $workPlan = $values;

                    return $manager;
                },
            );

        $parametersBag = new ParametersBag();

        (new CreateWindowReference())($manager, $parametersBag, FormWindow::class, 'formReference', 'secondReference');

        $this->assertSame(['formReference'], array_keys($workPlan));
        $this->assertSame(['secondReference' => $workPlan['formReference']], $this->readView($parametersBag));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedClasses(): iterable
    {
        yield 'an unknown class' => ['Teknoo\\Tests\\East\\UX\\Missing\\Component'];
        yield 'a class which is not a window' => [stdClass::class];
        yield 'a window which is not Live' => [Window::class];
        yield 'another window which is not Live' => [LoginWindow::class];
        yield 'the abstract window' => [AbstractWindow::class];
    }

    #[DataProvider('refusedClasses')]
    public function testInvokeRefusesAClassWhichIsNotALiveWindow(string $windowClass): void
    {
        $errors = [];
        $manager = $this->createMock(ManagerInterface::class);
        $manager->expects($this->never())->method('updateWorkPlan');
        $manager->expects($this->once())
            ->method('error')
            ->willReturnCallback(
                static function (Throwable $error) use (&$errors, $manager): ManagerInterface {
                    $errors[] = $error;

                    return $manager;
                },
            );

        $parametersBag = new ParametersBag();

        $this->assertInstanceOf(
            CreateWindowReferenceInterface::class,
            (new CreateWindowReference())($manager, $parametersBag, $windowClass),
        );

        $this->assertInstanceOf(DomainException::class, $errors[0]);
        $this->assertSame(400, $errors[0]->getCode());
        $this->assertStringNotContainsString($windowClass, $errors[0]->getMessage());
        $this->assertSame([], $this->readView($parametersBag));
    }

    public function testInvokeRefusesAReferenceWithAMethodWhichIsNotALiveAction(): void
    {
        $window = new #[AsLiveComponent] class extends AbstractWindow {
            public function refresh(): void
            {
            }

            public function close(): void
            {
            }
        };

        $errors = [];
        $manager = $this->createMock(ManagerInterface::class);
        $manager->expects($this->never())->method('updateWorkPlan');
        $manager->expects($this->once())
            ->method('error')
            ->willReturnCallback(
                static function (Throwable $error) use (&$errors, $manager): ManagerInterface {
                    $errors[] = $error;

                    return $manager;
                },
            );

        $parametersBag = new ParametersBag();

        $this->assertInstanceOf(
            CreateWindowReferenceInterface::class,
            (new CreateWindowReference())($manager, $parametersBag, $window::class),
        );

        $this->assertInstanceOf(DomainException::class, $errors[0]);
        $this->assertSame(400, $errors[0]->getCode());
        $this->assertSame([], $this->readView($parametersBag));
    }

    public function testInvokeLetsTheErrorOfAnInvalidReferenceGoToTheChef(): void
    {
        $window = new #[AsLiveComponent] class extends AbstractWindow {
        };

        $this->expectException(InvalidArgumentException::class);

        (new CreateWindowReference())(
            $this->createStub(ManagerInterface::class),
            new ParametersBag(),
            $window::class,
        );
    }
}
