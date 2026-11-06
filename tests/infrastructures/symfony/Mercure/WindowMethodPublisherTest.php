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

namespace Teknoo\Tests\East\UXBundle\Mercure;

use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Teknoo\East\UX\Contracts\Window\WindowMethodPublisherInterface;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;
use Teknoo\East\UXBundle\Components\ListWindow;
use Teknoo\East\UXBundle\Mercure\WindowMethodPublisher;

/**
 * Publication of the name of a method of a window on the Mercure topic of its reference.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(WindowMethodPublisher::class)]
class WindowMethodPublisherTest extends TestCase
{
    private function buildReference(): WindowReference
    {
        return new WindowReference(ListWindow::class, 'foo', ['refresh', 'close']);
    }

    public function testPublishSendsOnlyTheNameOfTheMethodInAPrivateUpdate(): void
    {
        $updates = [];

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->willReturnCallback(
                static function (Update $update) use (&$updates): string {
                    $updates[] = $update;

                    return 'id';
                },
            );

        $publisher = new WindowMethodPublisher($hub);

        $this->assertInstanceOf(
            WindowMethodPublisherInterface::class,
            $publisher->publish($this->buildReference(), 'refresh'),
        );

        $this->assertCount(1, $updates);
        $this->assertSame(['https://teknoo.software/east-collection/ux/window/foo'], $updates[0]->getTopics());
        $this->assertSame('refresh', $updates[0]->getData());
        $this->assertTrue($updates[0]->isPrivate());
    }

    public function testThePatternCoversTheTopics(): void
    {
        $this->assertSame(
            WindowMethodPublisher::TOPIC_PREFIX . ':id',
            WindowMethodPublisher::TOPIC_PATTERN,
        );
    }

    public function testPublishRefusesAMethodMissingFromTheReference(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->never())->method('publish');

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(400);

        (new WindowMethodPublisher($hub))->publish($this->buildReference(), 'delete');
    }

    public function testPublishRefusesAMissingMethodEvenWithoutHub(): void
    {
        $this->expectException(DomainException::class);

        (new WindowMethodPublisher())->publish($this->buildReference(), 'delete');
    }

    public function testPublishWithoutHub(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(500);

        (new WindowMethodPublisher())->publish($this->buildReference(), 'refresh');
    }

    public function testPublishLetsTheErrorOfTheHubGo(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish')->willThrowException(new MercureRuntimeException('The hub is down'));

        $this->expectException(MercureRuntimeException::class);

        (new WindowMethodPublisher($hub))->publish($this->buildReference(), 'refresh');
    }
}
