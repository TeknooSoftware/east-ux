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
use ReflectionProperty;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Teknoo\East\UXBundle\Components\Pagination;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

use function array_intersect;
use function array_map;
use function dirname;
use function get_object_vars;
use function preg_replace;
use function trim;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(Pagination::class)]
class PaginationTest extends TestCase
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

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestStack = $this->createStub(RequestStack::class);
    }

    private function buildPagination(): Pagination
    {
        return new Pagination($this->requestStack);
    }

    /**
     * Renders the template as Symfony UX does (`this`, `attributes` and the public props), with a translator
     * returning `<key>|<domain>` (`default` when no domain is passed). Spaces between tags are collapsed.
     */
    private function render(Pagination $pagination): string
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

        $html = $twig->render(
            '@TeknooEastUX/components/Pagination.html.twig',
            [
                'this' => $pagination,
                'attributes' => new ComponentAttributes([], $escaper),
            ] + get_object_vars($pagination),
        );

        return trim((string) preg_replace('/\s+/', ' ', $html));
    }

    public function testConstruct(): void
    {
        $pagination = $this->buildPagination();

        $this->assertInstanceOf(Pagination::class, $pagination);
    }

    public function testDefaultProperties(): void
    {
        $pagination = $this->buildPagination();

        $this->assertEquals(0, $pagination->pageCount);
        $this->assertEquals(1, $pagination->page);
    }

    public function testBuildUrlWithNoRequest(): void
    {
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);

        $pagination = new Pagination($requestStack);

        $url = $pagination->buildUrl(2);

        $this->assertEquals('?page=2', $url);
    }

    public function testBuildUrlWithEmptyQueryParams(): void
    {
        $request = new Request();

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $pagination = new Pagination($requestStack);

        $url = $pagination->buildUrl(3);

        $this->assertEquals('?page=3', $url);
    }

    public function testBuildUrlWithExistingQueryParams(): void
    {
        $request = new Request(['filter' => 'active', 'sort' => 'name']);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $pagination = new Pagination($requestStack);

        $url = $pagination->buildUrl(2);

        $this->assertStringContainsString('filter=active', $url);
        $this->assertStringContainsString('sort=name', $url);
        $this->assertStringContainsString('page=2', $url);
        $this->assertStringStartsWith('?', $url);
    }

    public function testBuildUrlReplacesExistingPageParam(): void
    {
        $request = new Request(['page' => '1', 'filter' => 'active']);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $pagination = new Pagination($requestStack);

        $url = $pagination->buildUrl(5);

        $this->assertStringContainsString('page=5', $url);
        $this->assertStringNotContainsString('page=1', $url);
        $this->assertStringContainsString('filter=active', $url);
    }

    public function testPropertiesCanBeSet(): void
    {
        $pagination = $this->buildPagination();

        $pagination->pageCount = 10;
        $pagination->page = 3;

        $this->assertEquals(10, $pagination->pageCount);
        $this->assertEquals(3, $pagination->page);
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $nonPublicProperties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(Pagination::class))->getProperties(
                ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE,
            ),
        );

        $this->assertSame([], array_intersect($nonPublicProperties, self::EAST_VIEW_PARAMETERS));
    }

    public function testRenderNothingWithASinglePage(): void
    {
        $pagination = $this->buildPagination();
        $pagination->pageCount = 1;

        $this->assertSame('', $this->render($pagination));
    }

    public function testRenderFirstPage(): void
    {
        $pagination = $this->buildPagination();
        $pagination->pageCount = 3;
        $pagination->page = 1;

        $html = $this->render($pagination);

        $this->assertStringContainsString(
            '<nav aria-label="teknoo.east.ux.pagination.text.pagination|east_ux" class="mt-3">',
            $html,
        );
        $this->assertStringContainsString(
            '<li class="page-item disabled"> '
            . '<span class="page-link">teknoo.east.ux.pagination.action.previous|east_ux</span> </li>',
            $html,
        );
        $this->assertStringContainsString(
            '<li class="page-item active"> <button type="button" class="page-link" data-model="page" '
            . 'data-action="live#update" data-value="1" value="1"> 1 </button> </li>',
            $html,
        );
        $this->assertStringContainsString('data-value="3" value="3"> 3 </button>', $html);
        $this->assertStringContainsString(
            'data-value="2" value="2"> teknoo.east.ux.pagination.action.next|east_ux </button>',
            $html,
        );
    }

    public function testRenderLastPage(): void
    {
        $pagination = $this->buildPagination();
        $pagination->pageCount = 3;
        $pagination->page = 3;

        $html = $this->render($pagination);

        $this->assertStringContainsString(
            'data-value="2" value="2"> teknoo.east.ux.pagination.action.previous|east_ux </button>',
            $html,
        );
        $this->assertStringContainsString(
            '<li class="page-item disabled"> '
            . '<span class="page-link">teknoo.east.ux.pagination.action.next|east_ux</span> </li>',
            $html,
        );
        $this->assertStringContainsString(
            '<li class="page-item active"> <button type="button" class="page-link" data-model="page" '
            . 'data-action="live#update" data-value="3" value="3"> 3 </button> </li>',
            $html,
        );
    }
}
