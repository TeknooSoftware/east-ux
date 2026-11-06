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

namespace Teknoo\Tests\East\UXBundle\Hydration;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;
use Teknoo\East\UXBundle\Components\ListWindow;
use Teknoo\East\UXBundle\Hydration\WindowReferenceHydrationExtension;

/**
 * Hydration of a `WindowReference` in a LiveProp.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(WindowReferenceHydrationExtension::class)]
class WindowReferenceHydrationExtensionTest extends TestCase
{
    public function testSupports(): void
    {
        $extension = new WindowReferenceHydrationExtension();

        $this->assertTrue($extension->supports(WindowReference::class));
        $this->assertFalse($extension->supports(stdClass::class));
    }

    public function testDehydrateAndHydrateTheSameReference(): void
    {
        $extension = new WindowReferenceHydrationExtension();
        $reference = new WindowReference(ListWindow::class, 'foo', ['refresh', 'close']);

        $dehydrated = $extension->dehydrate($reference);

        $this->assertSame(
            ['componentClass' => ListWindow::class, 'id' => 'foo', 'methods' => ['refresh', 'close']],
            $dehydrated,
        );
        $this->assertEquals($reference, $extension->hydrate($dehydrated, WindowReference::class));
    }

    public function testHydrateValidatesTheReferenceAgain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WindowReferenceHydrationExtension())->hydrate(
            ['componentClass' => ListWindow::class, 'id' => 'foo', 'methods' => ['missing']],
            WindowReference::class,
        );
    }

    public function testDehydrateRefusesAnotherObject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WindowReferenceHydrationExtension())->dehydrate(new stdClass());
    }
}
