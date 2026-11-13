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

namespace Teknoo\Tests\East\UXBundle\ComponentRendering\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Validator\Validation;
use Teknoo\East\UXBundle\ComponentRendering\Exception\InvalidPropsException;
use Teknoo\East\UXBundle\ComponentRendering\Validator\ObjectWindowPropsValidator;
use Teknoo\Recipe\Promise\PromiseInterface;

/**
 * Unit test for the validator of the props of the `ObjectWindow` sent to the component renderer.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ObjectWindowPropsValidator::class)]
class ObjectWindowPropsValidatorTest extends TestCase
{
    /**
     * The components allowed as content of the window
     */
    private const array CONTENT_COMPONENTS = ['ItemView', 'ItemCard'];

    private function buildValidator(): ObjectWindowPropsValidator
    {
        return new ObjectWindowPropsValidator(Validation::createValidator(), self::CONTENT_COMPONENTS);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function assertValid(array $props): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $promise->expects($this->once())->method('success')->with($props)->willReturnSelf();
        $promise->expects($this->never())->method('fail');

        $validator = $this->buildValidator();
        $this->assertSame($validator, $validator->validate($props, $promise));
    }

    /**
     * @return array<string, mixed>
     */
    private static function requiredProps(): array
    {
        return [
            'windowId' => 'item-42',
            'originalPath' => '/items/42',
            'contentComponent' => 'ItemView',
            'floating' => true,
        ];
    }

    public function testValidateTheRequiredProps(): void
    {
        $this->assertValid(self::requiredProps());
    }

    public function testValidateEachAllowedContentComponent(): void
    {
        foreach (self::CONTENT_COMPONENTS as $component) {
            $this->assertValid(['contentComponent' => $component] + self::requiredProps());
        }
    }

    public function testValidateAllTheProps(): void
    {
        $this->assertValid(
            self::requiredProps() + [
                'hidden' => true,
                'closable' => true,
                'resizable' => false,
                'draggable' => true,
                'title' => 'Items - View',
                'closeAriaLabel' => 'Close',
                'resizeAriaLabel' => 'Resize',
            ]
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPropsProvider(): iterable
    {
        $props = self::requiredProps();

        foreach ($props as $name => $value) {
            $missing = $props;
            unset($missing[$name]);

            yield "missing $name" => [$missing];
        }

        yield 'blank windowId' => [['windowId' => ''] + $props];
        yield 'windowId not a string' => [['windowId' => 42] + $props];
        yield 'blank originalPath' => [['originalPath' => ''] + $props];
        yield 'originalPath not a string' => [['originalPath' => ['/items/42']] + $props];
        yield 'blank contentComponent' => [['contentComponent' => ''] + $props];
        yield 'contentComponent not a string' => [['contentComponent' => 42] + $props];
        yield 'contentComponent out of the list' => [['contentComponent' => 'ListWindow'] + $props];
        yield 'contentComponent nesting the window' => [['contentComponent' => 'ObjectWindow'] + $props];
        yield 'contentComponent in another case' => [['contentComponent' => 'itemview'] + $props];
        yield 'floating false' => [['floating' => false] + $props];
        yield 'floating not a bool' => [['floating' => 'true'] + $props];
        yield 'hidden not a bool' => [$props + ['hidden' => 1]];
        yield 'closable not a bool' => [$props + ['closable' => 'yes']];
        yield 'resizable not a bool' => [$props + ['resizable' => 0]];
        yield 'draggable not a bool' => [$props + ['draggable' => 'yes']];
        yield 'title not a string' => [$props + ['title' => 42]];
        yield 'closeAriaLabel not a string' => [$props + ['closeAriaLabel' => ['Close']]];
        yield 'resizeAriaLabel not a string' => [$props + ['resizeAriaLabel' => 42]];

        // None of the properties of these props is nullable: `null` would end in a `TypeError` of the component
        foreach (['windowId', 'originalPath', 'contentComponent', 'floating'] as $name) {
            yield "$name null" => [[$name => null] + $props];
        }

        $optional = ['hidden', 'closable', 'resizable', 'draggable', 'title', 'closeAriaLabel', 'resizeAriaLabel'];
        foreach ($optional as $name) {
            yield "$name null" => [$props + [$name => null]];
        }

        // The object comes only from the recipe of `originalPath`, behind its access control
        yield 'objectInstance' => [$props + ['objectInstance' => new stdClass()]];
        yield 'objectInstance as a string' => [$props + ['objectInstance' => '42']];
        yield 'windowReference' => [$props + ['windowReference' => ['id' => 'forged']]];
        yield 'closeRedirectUrl' => [$props + ['closeRedirectUrl' => '/items/']];
        yield 'object' => [$props + ['object' => '42']];
        yield '@checksum' => [$props + ['@checksum' => 'forged']];
    }

    /**
     * @param array<string, mixed> $props
     */
    #[DataProvider('invalidPropsProvider')]
    public function testRefuseInvalidProps(array $props): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $promise->expects($this->never())->method('success');
        $promise->expects($this->once())
            ->method('fail')
            ->with($this->isInstanceOf(InvalidPropsException::class))
            ->willReturnSelf();

        $validator = $this->buildValidator();
        $this->assertSame($validator, $validator->validate($props, $promise));
    }
}
