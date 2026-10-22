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
use Symfony\UX\TwigComponent\ComponentAttributes;
use Teknoo\East\UXBundle\Components\Card;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

use function array_intersect;
use function array_map;
use function dirname;
use function get_object_vars;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(Card::class)]
class CardTest extends TestCase
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

    private function render(Card $card): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');

        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render(
            '@TeknooEastUX/components/Card.html.twig',
            [
                'this' => $card,
                'attributes' => new ComponentAttributes([], $escaper),
            ] + get_object_vars($card),
        );
    }

    public function testConstruct(): void
    {
        $card = new Card();

        $this->assertInstanceOf(Card::class, $card);
    }

    public function testDefaultProperties(): void
    {
        $card = new Card();

        $this->assertEquals('', $card->title);
        $this->assertEquals('', $card->content);
        $this->assertFalse($card->contentIsHtml);
        $this->assertEquals('', $card->leftActionPath);
        $this->assertEquals('', $card->leftActionText);
        $this->assertEquals('', $card->rightActionPath);
        $this->assertEquals('', $card->rightActionText);
    }

    public function testPropertiesCanBeSet(): void
    {
        $card = new Card();

        $card->title = 'Test Card';
        $card->content = 'Card content';
        $card->contentIsHtml = true;
        $card->leftActionPath = '/left';
        $card->leftActionText = 'Left Action';
        $card->rightActionPath = '/right';
        $card->rightActionText = 'Right Action';

        $this->assertEquals('Test Card', $card->title);
        $this->assertEquals('Card content', $card->content);
        $this->assertTrue($card->contentIsHtml);
        $this->assertEquals('/left', $card->leftActionPath);
        $this->assertEquals('Left Action', $card->leftActionText);
        $this->assertEquals('/right', $card->rightActionPath);
        $this->assertEquals('Right Action', $card->rightActionText);
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $nonPublicProperties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(Card::class))->getProperties(
                ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE,
            ),
        );

        $this->assertSame([], array_intersect($nonPublicProperties, self::EAST_VIEW_PARAMETERS));
    }

    public function testRenderEscapesContentAndKeepsLineBreaks(): void
    {
        $card = new Card();
        $card->title = 'Title <i>1</i>';
        $card->content = "Hello <script>alert('x')</script>\nWorld";

        $html = $this->render($card);

        $this->assertStringContainsString('<h5 class="card-title">Title &lt;i&gt;1&lt;/i&gt;</h5>', $html);
        $this->assertStringContainsString(
            'Hello &lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;<br />' . "\n" . 'World',
            $html,
        );
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderContentAsHtmlWhenContentIsHtml(): void
    {
        $card = new Card();
        $card->content = "Hello <strong>World</strong>\nAgain";
        $card->contentIsHtml = true;

        $html = $this->render($card);

        $this->assertStringContainsString('Hello <strong>World</strong><br />' . "\n" . 'Again', $html);
    }

    public function testRenderWithoutAction(): void
    {
        $card = new Card();
        $card->title = 'Title';
        $card->content = 'Content';
        $card->leftActionPath = '/left';
        $card->rightActionText = 'Right';

        $html = $this->render($card);

        $this->assertStringNotContainsString('class="btn', $html);
        $this->assertStringNotContainsString('d-flex', $html);
    }

    public function testRenderWithLeftActionOnly(): void
    {
        $card = new Card();
        $card->leftActionPath = '/left';
        $card->leftActionText = 'Left <b>';

        $html = $this->render($card);

        $this->assertStringContainsString('justify-content-start', $html);
        $this->assertStringContainsString('<a class="btn" href="/left">Left &lt;b&gt;</a>', $html);
        $this->assertStringNotContainsString('btn-secondary', $html);
    }

    public function testRenderWithRightActionOnly(): void
    {
        $card = new Card();
        $card->rightActionPath = '/right';
        $card->rightActionText = 'Right';

        $html = $this->render($card);

        $this->assertStringContainsString('justify-content-end', $html);
        $this->assertStringContainsString('<a class="btn btn-secondary" href="/right">Right</a>', $html);
    }

    public function testRenderWithBothActions(): void
    {
        $card = new Card();
        $card->leftActionPath = '/left';
        $card->leftActionText = 'Left';
        $card->rightActionPath = '/right';
        $card->rightActionText = 'Right';

        $html = $this->render($card);

        $this->assertStringContainsString('justify-content-between', $html);
        $this->assertStringContainsString('<a class="btn" href="/left">Left</a>', $html);
        $this->assertStringContainsString('<a class="btn btn-secondary" href="/right">Right</a>', $html);
    }
}
