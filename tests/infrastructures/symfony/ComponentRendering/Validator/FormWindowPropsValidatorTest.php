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
use Teknoo\East\UXBundle\ComponentRendering\Validator\FormWindowPropsValidator;
use Teknoo\Recipe\Promise\PromiseInterface;
use Teknoo\Tests\East\UX\Support\Fixtures\Form\Type\ItemType;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(FormWindowPropsValidator::class)]
class FormWindowPropsValidatorTest extends TestCase
{
    private function buildValidator(): FormWindowPropsValidator
    {
        return new FormWindowPropsValidator(Validation::createValidator());
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
            'formPath' => '/items/edit/42',
            'formName' => ItemType::class,
        ];
    }

    public function testValidateTheRequiredProps(): void
    {
        $this->assertValid(self::requiredProps());
    }

    public function testValidateThePropsSentByTheDynamicLoader(): void
    {
        $this->assertValid(
            self::requiredProps() + [
                'originalPath' => '/items/edit/42',
                'objectId' => '42',
                'title' => 'Item 42',
                'closeAriaLabel' => 'Close',
                'closable' => true,
                'draggable' => true,
                'hidden' => true,
            ]
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPropsProvider(): iterable
    {
        $props = self::requiredProps();

        yield 'missing windowId' => [['formPath' => $props['formPath'], 'formName' => $props['formName']]];
        yield 'missing formPath' => [['windowId' => $props['windowId'], 'formName' => $props['formName']]];
        yield 'missing formName' => [['windowId' => $props['windowId'], 'formPath' => $props['formPath']]];
        yield 'blank windowId' => [['windowId' => ''] + $props];
        yield 'blank formPath' => [['formPath' => ''] + $props];
        yield 'formName not a string' => [['formName' => 42] + $props];
        yield 'formName not a form type' => [['formName' => stdClass::class] + $props];
        yield 'formName not a class' => [['formName' => 'Foo\\Bar'] + $props];
        yield 'title not a string' => [$props + ['title' => 42]];
        yield 'closable not a bool' => [$props + ['closable' => 'yes']];
        yield 'hidden not a bool' => [$props + ['hidden' => 1]];
        yield 'objectInstance' => [$props + ['objectInstance' => 'item-1']];
        yield 'formTheme' => [$props + ['formTheme' => 'evil.html.twig']];
        yield 'isLive' => [$props + ['isLive' => false]];
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
