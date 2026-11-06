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

namespace Teknoo\Tests\East\UX\Object\DTO\Window;

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;

use function serialize;
use function unserialize;

/**
 * Reference of a window, to call its methods from outside of the page.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(WindowReference::class)]
class WindowReferenceTest extends TestCase
{
    /**
     * @return class-string
     */
    private function buildComponentClass(): string
    {
        $component = new class {
            public function refresh(): void
            {
            }

            public function close(): void
            {
            }

            public function withParameter(string $value): void
            {
            }

            public function withOptionalParameter(string $value = ''): void
            {
            }

            public function withReturnValue(): string
            {
                return '';
            }

            public function withoutReturnType()
            {
            }

            protected function notPublic(): void
            {
            }

            private function secret(): void
            {
            }
        };

        return $component::class;
    }

    public function testConstruct(): void
    {
        $componentClass = $this->buildComponentClass();

        $reference = new WindowReference(
            componentClass: $componentClass,
            id: 'foo',
            methods: ['refresh', 'close'],
        );

        $this->assertInstanceOf(WindowReference::class, $reference);
        $this->assertSame($componentClass, $reference->componentClass);
        $this->assertSame('foo', $reference->id);
        $this->assertSame(['refresh', 'close'], $reference->methods);
    }

    public function testConstructWithoutMethod(): void
    {
        $reference = new WindowReference(
            componentClass: $this->buildComponentClass(),
            id: 'foo',
            methods: [],
        );

        $this->assertSame([], $reference->methods);
    }

    public function testIsReadOnly(): void
    {
        $reference = new WindowReference(
            componentClass: $this->buildComponentClass(),
            id: 'foo',
            methods: ['refresh'],
        );

        $this->expectException(Error::class);

        /* @phpstan-ignore-next-line */
        $reference->id = 'bar';
    }

    public function testConstructRefusesAnUnknownClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(500);

        new WindowReference(
            componentClass: 'Teknoo\\Tests\\East\\UX\\Missing\\Component',
            id: 'foo',
            methods: ['refresh'],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMethods(): iterable
    {
        yield 'a missing method' => ['missing'];
        yield 'a protected method' => ['notPublic'];
        yield 'a private method' => ['secret'];
        yield 'a method with a parameter' => ['withParameter'];
        yield 'a method with an optional parameter' => ['withOptionalParameter'];
        yield 'a method with a return value' => ['withReturnValue'];
        yield 'a method without return type' => ['withoutReturnType'];
    }

    #[DataProvider('invalidMethods')]
    public function testConstructRefusesAnInvalidMethod(string $method): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(500);

        new WindowReference(
            componentClass: $this->buildComponentClass(),
            id: 'foo',
            methods: ['refresh', $method],
        );
    }

    public function testSerialization(): void
    {
        $reference = new WindowReference(
            componentClass: $this->buildComponentClass(),
            id: 'foo',
            methods: ['refresh', 'close'],
        );

        $unserialized = unserialize(serialize($reference));

        $this->assertInstanceOf(WindowReference::class, $unserialized);
        $this->assertNotSame($reference, $unserialized);
        $this->assertEquals($reference, $unserialized);
    }
}
