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

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Teknoo\East\CommonBundle\Contracts\Rendering\ComponentFactoryInterface;
use Teknoo\East\UXBundle\ComponentRendering\Exception\InvalidPropsException;
use Teknoo\East\UXBundle\ComponentRendering\Validator\ListWindowPropsValidator;
use Teknoo\Recipe\Promise\PromiseInterface;
use Teknoo\Tests\East\UX\Support\Fixtures\Form\Type\ItemType;

use function in_array;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ListWindowPropsValidator::class)]
class ListWindowPropsValidatorTest extends TestCase
{
    /**
     * The routes known by the URL generator stub, and the route needing a parameter
     */
    private const array ROUTES = [
        'app_item_new' => '/items/new',
        'app_item_list' => '/items/',
    ];

    private const string ROUTE_WITH_PARAMETERS = 'app_item_edit';

    /**
     * The components known by the component factory stub
     */
    private const array COMPONENTS = ['ItemRow', 'Card'];

    private function buildValidator(): ListWindowPropsValidator
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $name): string {
                if (self::ROUTE_WITH_PARAMETERS === $name) {
                    throw new MissingMandatoryParametersException($name, ['id']);
                }

                if (!isset(self::ROUTES[$name])) {
                    throw new RouteNotFoundException("Unknown route $name");
                }

                return self::ROUTES[$name];
            }
        );

        $componentFactory = $this->createStub(ComponentFactoryInterface::class);
        $componentFactory->method('metadataFor')->willReturnCallback(
            static function (string $name): ComponentMetadata {
                if (!in_array($name, self::COMPONENTS, true)) {
                    throw new InvalidArgumentException("Unknown component \"$name\".");
                }

                return new ComponentMetadata(['key' => $name, 'template' => $name . '.html.twig']);
            }
        );

        return new ListWindowPropsValidator(Validation::createValidator(), $urlGenerator, $componentFactory);
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
            'windowId' => 'items',
            'originalPath' => '/items/',
            'itemComponent' => 'ItemRow',
            'floating' => true,
        ];
    }

    public function testValidateTheRequiredProps(): void
    {
        $this->assertValid(self::requiredProps());
    }

    public function testValidateAllTheProps(): void
    {
        $this->assertValid(
            self::requiredProps() + [
                'hidden' => true,
                'closable' => true,
                'resizable' => false,
                'draggable' => true,
                'title' => 'Items',
                'closeAriaLabel' => 'Close',
                'resizeAriaLabel' => 'Resize',
                'tableHeaders' => ['app.item.name', 'app.item.actions'],
                'searchFormName' => ItemType::class,
                'newFormName' => ItemType::class,
                'newFormRoute' => 'app_item_new',
                'newButtonLabel' => 'app.item.new',
                'newFormTitle' => 'app.item.title.new',
                'page' => 2,
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
        yield 'originalPath not a string' => [['originalPath' => ['/items/']] + $props];
        yield 'blank itemComponent' => [['itemComponent' => ''] + $props];
        yield 'itemComponent not a string' => [['itemComponent' => 42] + $props];
        yield 'unknown itemComponent' => [['itemComponent' => 'UnknownRow'] + $props];
        yield 'floating false' => [['floating' => false] + $props];
        yield 'floating null' => [['floating' => null] + $props];
        yield 'floating not a bool' => [['floating' => 'true'] + $props];
        yield 'hidden not a bool' => [$props + ['hidden' => 1]];
        yield 'draggable not a bool' => [$props + ['draggable' => 'yes']];
        yield 'title not a string' => [$props + ['title' => 42]];
        yield 'tableHeaders not an array' => [$props + ['tableHeaders' => 'app.item.name']];
        yield 'tableHeaders with an int' => [$props + ['tableHeaders' => ['app.item.name', 42]]];
        yield 'searchFormName not a form type' => [$props + ['searchFormName' => stdClass::class]];
        yield 'searchFormName not a class' => [$props + ['searchFormName' => 'Foo\\Bar']];
        yield 'newFormName not a form type' => [$props + ['newFormName' => stdClass::class]];
        yield 'newFormName not a string' => [$props + ['newFormName' => 42]];
        yield 'blank newFormRoute' => [$props + ['newFormRoute' => '']];
        yield 'newFormRoute not a string' => [$props + ['newFormRoute' => 42]];
        yield 'unknown newFormRoute' => [$props + ['newFormRoute' => 'app_unknown']];
        yield 'newFormRoute with parameters' => [$props + ['newFormRoute' => self::ROUTE_WITH_PARAMETERS]];
        yield 'page 0' => [$props + ['page' => 0]];
        yield 'page not an int' => [$props + ['page' => '2']];
        yield 'closeRedirectUrl' => [$props + ['closeRedirectUrl' => '/items/']];
        yield 'objectsCollection' => [$props + ['objectsCollection' => ['item-1']]];
        yield 'pageCount' => [$props + ['pageCount' => 3]];
        yield 'formView' => [$props + ['formView' => 'item']];
        yield 'formValues' => [$props + ['formValues' => ['name' => 'Item']]];
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
