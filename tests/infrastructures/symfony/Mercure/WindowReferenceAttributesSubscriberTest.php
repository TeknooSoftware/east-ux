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

namespace Teknoo\Tests\East\UXBundle\Mercure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Twig\MercureExtension;
use Symfony\Component\Mercure\Update;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;
use Symfony\UX\TwigComponent\MountedComponent;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;
use Teknoo\East\UXBundle\Components\AbstractWindow;
use Teknoo\East\UXBundle\Components\ListWindow;
use Teknoo\East\UXBundle\Mercure\WindowReferenceAttributesSubscriber;
use Twig\Runtime\EscaperRuntime;

/**
 * Subscription of a Live window with a reference to its Mercure topic.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(WindowReferenceAttributesSubscriber::class)]
class WindowReferenceAttributesSubscriberTest extends TestCase
{
    private function buildMercure(): MercureExtension
    {
        return new MercureExtension(
            new HubRegistry(
                new MockHub(
                    'https://hub.internal/.well-known/mercure',
                    new StaticTokenProvider('token'),
                    static fn (Update $update): string => 'id',
                    publicUrl: 'https://hub.example/.well-known/mercure',
                ),
            ),
        );
    }

    private function buildWindow(?WindowReference $reference): AbstractWindow
    {
        $window = new class extends AbstractWindow {
        };
        $window->windowReference = $reference;

        return $window;
    }

    private function buildReference(): WindowReference
    {
        return new WindowReference(ListWindow::class, 'foo', ['refresh', 'close']);
    }

    /**
     * @param array<string, mixed>|null $variables
     */
    private function buildEvent(object $component, bool $live = true, ?array $variables = null): PreRenderEvent
    {
        $attributes = new ComponentAttributes(['id' => 'bar'], new EscaperRuntime());

        return new PreRenderEvent(
            new MountedComponent('ListWindow', $component, $attributes),
            new ComponentMetadata(['template' => 'list.html.twig', 'live' => $live]),
            $variables ?? ['attributes' => $attributes],
        );
    }

    public function testSubscribesToTheEventOfTheRendering(): void
    {
        $this->assertSame(
            [PreRenderEvent::class => 'onPreRender'],
            WindowReferenceAttributesSubscriber::getSubscribedEvents(),
        );
    }

    public function testAddsTheControllerAndTheUrlOfTheTopic(): void
    {
        $event = $this->buildEvent($this->buildWindow($this->buildReference()));

        (new WindowReferenceAttributesSubscriber($this->buildMercure()))->onPreRender($event);

        $attributes = $event->getVariables()['attributes'];
        $this->assertInstanceOf(ComponentAttributes::class, $attributes);
        $this->assertSame(
            [
                'data-controller' => 'window-reference',
                'data-window-reference-url-value' => 'https://hub.example/.well-known/mercure'
                    . '?match=https%3A%2F%2Fteknoo.software%2Feast-collection%2Fux%2Fwindow%2Ffoo',
                'id' => 'bar',
            ],
            $attributes->all(),
        );
    }

    public function testDoesNothingWithoutReference(): void
    {
        $event = $this->buildEvent($this->buildWindow(null));
        $variables = $event->getVariables();

        (new WindowReferenceAttributesSubscriber($this->buildMercure()))->onPreRender($event);

        $this->assertSame($variables, $event->getVariables());
    }

    public function testDoesNothingWithoutMercure(): void
    {
        $event = $this->buildEvent($this->buildWindow($this->buildReference()));
        $variables = $event->getVariables();

        (new WindowReferenceAttributesSubscriber())->onPreRender($event);

        $this->assertSame($variables, $event->getVariables());
    }

    public function testDoesNothingForAComponentWhichIsNotAWindow(): void
    {
        $event = $this->buildEvent(new stdClass());
        $variables = $event->getVariables();

        (new WindowReferenceAttributesSubscriber($this->buildMercure()))->onPreRender($event);

        $this->assertSame($variables, $event->getVariables());
    }

    public function testDoesNothingForAWindowWhichIsNotLive(): void
    {
        $event = $this->buildEvent($this->buildWindow($this->buildReference()), false);
        $variables = $event->getVariables();

        (new WindowReferenceAttributesSubscriber($this->buildMercure()))->onPreRender($event);

        $this->assertSame($variables, $event->getVariables());
    }

    public function testDoesNothingWithoutAttributes(): void
    {
        $event = $this->buildEvent($this->buildWindow($this->buildReference()), true, ['title' => 'foo']);

        (new WindowReferenceAttributesSubscriber($this->buildMercure()))->onPreRender($event);

        $this->assertSame(['title' => 'foo'], $event->getVariables());
    }
}
