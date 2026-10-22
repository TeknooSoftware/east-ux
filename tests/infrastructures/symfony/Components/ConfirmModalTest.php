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
use Teknoo\East\UXBundle\Components\ConfirmModal;
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
#[CoversClass(ConfirmModal::class)]
class ConfirmModalTest extends TestCase
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

    private function render(ConfirmModal $modal): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');

        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render(
            '@TeknooEastUX/components/ConfirmModal.html.twig',
            [
                'this' => $modal,
                'attributes' => new ComponentAttributes([], $escaper),
            ] + get_object_vars($modal),
        );
    }

    public function testConstruct(): void
    {
        $modal = new ConfirmModal();

        $this->assertInstanceOf(ConfirmModal::class, $modal);
    }

    public function testDefaultProperties(): void
    {
        $modal = new ConfirmModal();

        $this->assertEquals('', $modal->modalId);
        $this->assertEquals('Confirm', $modal->title);
        $this->assertEquals('Are you sure?', $modal->message);
        $this->assertEquals('Confirm', $modal->confirmText);
        $this->assertEquals('Cancel', $modal->cancelText);
        $this->assertEquals('btn-danger', $modal->confirmClass);
    }

    public function testPropertiesCanBeSet(): void
    {
        $modal = new ConfirmModal();

        $modal->modalId = 'test-modal';
        $modal->title = 'Delete Confirmation';
        $modal->message = 'Are you sure you want to delete this item?';
        $modal->confirmText = 'Delete';
        $modal->cancelText = 'Keep';
        $modal->confirmClass = 'btn-warning';

        $this->assertEquals('test-modal', $modal->modalId);
        $this->assertEquals('Delete Confirmation', $modal->title);
        $this->assertEquals('Are you sure you want to delete this item?', $modal->message);
        $this->assertEquals('Delete', $modal->confirmText);
        $this->assertEquals('Keep', $modal->cancelText);
        $this->assertEquals('btn-warning', $modal->confirmClass);
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $nonPublicProperties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(ConfirmModal::class))->getProperties(
                ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE,
            ),
        );

        $this->assertSame([], array_intersect($nonPublicProperties, self::EAST_VIEW_PARAMETERS));
    }

    public function testRender(): void
    {
        $modal = new ConfirmModal();
        $modal->modalId = 'delete-confirm-modal';
        $modal->title = 'Delete <b>it</b>';
        $modal->message = 'Are you sure? <script>';
        $modal->confirmText = 'Delete';
        $modal->cancelText = 'Keep';
        $modal->confirmClass = 'btn-warning';

        $html = $this->render($modal);

        $this->assertStringContainsString('<div class="modal fade" id="delete-confirm-modal"', $html);
        $this->assertStringContainsString('<div class="window confirm-modal modal-content">', $html);
        $this->assertStringContainsString('<h1 class="title">Delete &lt;b&gt;it&lt;/b&gt;</h1>', $html);
        $this->assertStringContainsString('<p>Are you sure? &lt;script&gt;</p>', $html);
        $this->assertStringContainsString('aria-label="Keep"', $html);
        $this->assertStringContainsString('<button type="button" class="btn btn-warning" data-confirm-button>', $html);
        $this->assertStringContainsString('Delete', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
