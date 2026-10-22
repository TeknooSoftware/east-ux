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

namespace Teknoo\Tests\East\UXBundle\Components;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Teknoo\East\UXBundle\Components\AbstractWindow;

/**
 * Live Twig component rendering the abstract window interface.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(AbstractWindow::class)]
class AbstractWindowTest extends TestCase
{
    private function buildConcreteWindow(): AbstractWindow
    {
        return new class extends AbstractWindow {
        };
    }

    public function testConstruct(): void
    {
        $window = $this->buildConcreteWindow();

        $this->assertInstanceOf(AbstractWindow::class, $window);
    }

    public function testIsAbstract(): void
    {
        $this->assertTrue((new ReflectionClass(AbstractWindow::class))->isAbstract());
    }

    public function testDefaultProperties(): void
    {
        $window = $this->buildConcreteWindow();

        $this->assertEquals('', $window->title);
        $this->assertFalse($window->closable);
        $this->assertFalse($window->resizable);
        $this->assertFalse($window->draggable);
        $this->assertEquals('', $window->windowId);
        $this->assertEquals('', $window->closeRedirectUrl);
        $this->assertEquals('Close', $window->closeAriaLabel);
        $this->assertEquals('Resize', $window->resizeAriaLabel);
    }

    public function testIsClosable(): void
    {
        $window = $this->buildConcreteWindow();

        $this->assertFalse($window->isClosable());

        $window->closable = true;
        $this->assertTrue($window->isClosable());
    }

    public function testIsResizable(): void
    {
        $window = $this->buildConcreteWindow();

        $this->assertFalse($window->isResizable());

        $window->resizable = true;
        $this->assertTrue($window->isResizable());
    }

    public function testIsDraggable(): void
    {
        $window = $this->buildConcreteWindow();

        $this->assertFalse($window->isDraggable());

        $window->draggable = true;
        $this->assertTrue($window->isDraggable());
    }

    public function testIsFixed(): void
    {
        $window = $this->buildConcreteWindow();

        $this->assertFalse($window->isFixed());

        $window->draggable = true;
        $this->assertFalse($window->isFixed());

        $window->draggable = false;
        $window->closable = true;
        $this->assertFalse($window->isFixed());

        $window->draggable = true;
        $window->closable = true;
        $this->assertTrue($window->isFixed());
    }

    public function testPropertiesCanBeSet(): void
    {
        $window = $this->buildConcreteWindow();

        $window->title = 'Test Window';
        $window->closable = true;
        $window->resizable = true;
        $window->draggable = true;
        $window->windowId = 'test-window';
        $window->closeRedirectUrl = '/redirect';
        $window->closeAriaLabel = 'Custom Close';
        $window->resizeAriaLabel = 'Custom Resize';

        $this->assertEquals('Test Window', $window->title);
        $this->assertTrue($window->closable);
        $this->assertTrue($window->resizable);
        $this->assertTrue($window->draggable);
        $this->assertEquals('test-window', $window->windowId);
        $this->assertEquals('/redirect', $window->closeRedirectUrl);
        $this->assertEquals('Custom Close', $window->closeAriaLabel);
        $this->assertEquals('Custom Resize', $window->resizeAriaLabel);
    }

    public function testAllPropertiesArePublic(): void
    {
        // East Common's LiveComponentBuilder assigns the view parameters of a recipe to the properties of the same
        // name: every property shared by the windows must be assignable from outside.
        foreach ((new ReflectionClass(AbstractWindow::class))->getProperties() as $property) {
            $this->assertTrue($property->isPublic(), $property->getName() . ' must be public');
            $this->assertFalse($property->isReadOnly(), $property->getName() . ' must not be readonly');
        }
    }
}
