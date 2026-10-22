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

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Teknoo\East\UXBundle\Components\ObjectList;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFunction;

use function array_intersect;
use function array_map;
use function dirname;
use function get_object_vars;
use function htmlspecialchars;
use function is_string;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ObjectList::class)]
class ObjectListTest extends TestCase
{
    /**
     * View parameters of the East recipes, assigned by East Common's `LiveComponentBuilder` to the component
     * properties of the same name.
     */
    private const array EAST_VIEW_PARAMETERS = [
        'error',
        'form',
        'formView',
        'isTranslatable',
        'objectInstance',
        'objectSaved',
        'objectsCollection',
        'objectViewKey',
        'page',
        'pageCount',
        'queryParams',
        'request',
        'searchForm',
    ];

    /**
     * Renders the template as Symfony UX does (`this`, `attributes` and the public props), with a translator
     * returning `<key>|<domain>` (`default` when no domain is passed) and a `component()` function rendering the
     * name of the row component and the `title` of the object.
     */
    private function render(ObjectList $list): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');

        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        $twig->addExtension(
            new TranslationExtension(
                new class () implements TranslatorInterface {
                    /**
                     * @param array<string, mixed> $parameters
                     */
                    public function trans(
                        string $id,
                        array $parameters = [],
                        ?string $domain = null,
                        ?string $locale = null,
                    ): string {
                        return $id . '|' . ($domain ?? 'default');
                    }

                    public function getLocale(): string
                    {
                        return 'en';
                    }
                }
            )
        );

        $twig->addFunction(
            new TwigFunction(
                'component',
                static function (string $name, array $props): string {
                    $title = '';
                    if (($props['object'] ?? null) instanceof stdClass && is_string($props['object']->title ?? null)) {
                        $title = $props['object']->title;
                    }

                    return '<tr data-row="' . $name . '">' . htmlspecialchars($title) . '</tr>';
                },
                ['is_safe' => ['html']],
            )
        );

        return $twig->render(
            '@TeknooEastUX/components/ObjectList.html.twig',
            [
                'this' => $list,
                'attributes' => new ComponentAttributes([], $escaper),
            ] + get_object_vars($list),
        );
    }

    private function buildObject(string $title): stdClass
    {
        $object = new stdClass();
        $object->title = $title;

        return $object;
    }

    public function testConstruct(): void
    {
        $list = new ObjectList();

        $this->assertInstanceOf(ObjectList::class, $list);
    }

    public function testDefaultProperties(): void
    {
        $list = new ObjectList();

        $this->assertEquals([], $list->objectsCollection);
        $this->assertEquals('', $list->itemComponent);
        $this->assertEquals([], $list->tableHeaders);
    }

    public function testHasObjectsWithEmptyArray(): void
    {
        $list = new ObjectList();
        $list->objectsCollection = [];

        $this->assertFalse($list->hasObjects());
    }

    public function testHasObjectsWithNonEmptyArray(): void
    {
        $list = new ObjectList();
        $list->objectsCollection = [new stdClass(), new stdClass()];

        $this->assertTrue($list->hasObjects());
    }

    public function testHasObjectsWithEmptyIterator(): void
    {
        $list = new ObjectList();
        $list->objectsCollection = new ArrayIterator([]);

        $this->assertFalse($list->hasObjects());
    }

    public function testHasObjectsWithNonEmptyIterator(): void
    {
        $list = new ObjectList();
        $list->objectsCollection = new ArrayIterator([new stdClass()]);

        $this->assertTrue($list->hasObjects());
    }

    public function testHasObjectsWithGenerator(): void
    {
        $list = new ObjectList();
        $generator = (function () {
            yield new stdClass();
        })();
        $list->objectsCollection = $generator;

        $this->assertTrue($list->hasObjects());
    }

    public function testHasObjectsWithEmptyGenerator(): void
    {
        $list = new ObjectList();
        $generator = (function () {
            return;
            yield;
        })();
        $list->objectsCollection = $generator;

        $this->assertFalse($list->hasObjects());
    }

    public function testPropertiesCanBeSet(): void
    {
        $list = new ObjectList();

        $objects = [new stdClass()];
        $list->objectsCollection = $objects;
        $list->itemComponent = 'ServiceListItem';
        $list->tableHeaders = ['Name', 'Description'];

        $this->assertSame($objects, $list->objectsCollection);
        $this->assertEquals('ServiceListItem', $list->itemComponent);
        $this->assertEquals(['Name', 'Description'], $list->tableHeaders);
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $nonPublicProperties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(ObjectList::class))->getProperties(
                ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE,
            ),
        );

        $this->assertSame([], array_intersect($nonPublicProperties, self::EAST_VIEW_PARAMETERS));
    }

    public function testRenderWithoutObjects(): void
    {
        $list = new ObjectList();
        $list->tableHeaders = ['app.header.name'];

        $html = $this->render($list);

        $this->assertStringContainsString('<div class="alert alert-info text-center">', $html);
        $this->assertStringContainsString('teknoo.east.ux.object_list.text.no_results|east_ux', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('app.header.name', $html);
    }

    public function testRenderWithObjectsAndItemComponent(): void
    {
        $list = new ObjectList();
        $list->objectsCollection = [$this->buildObject('First <b>'), $this->buildObject('Second')];
        $list->itemComponent = 'UserListItem';
        $list->tableHeaders = ['app.header.name', 'app.header.actions'];

        $html = $this->render($list);

        $this->assertStringContainsString('<table class="table table-hover">', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<th>app.header.name|default</th>', $html);
        $this->assertStringContainsString('<th class="text-end">app.header.actions|default</th>', $html);
        $this->assertStringContainsString('<tr data-row="UserListItem">First &lt;b&gt;</tr>', $html);
        $this->assertStringContainsString('<tr data-row="UserListItem">Second</tr>', $html);
        $this->assertStringNotContainsString('no_results', $html);
    }

    public function testRenderWithObjectsWithoutHeadersNorItemComponent(): void
    {
        $list = new ObjectList();
        $list->objectsCollection = new ArrayIterator([$this->buildObject('First')]);

        $html = $this->render($list);

        $this->assertStringContainsString('<table class="table table-hover">', $html);
        $this->assertStringNotContainsString('<thead>', $html);
        $this->assertStringNotContainsString('data-row', $html);
        $this->assertStringContainsString('<tbody>', $html);
    }
}
