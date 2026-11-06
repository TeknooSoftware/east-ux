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

namespace Teknoo\East\UXBundle\Mercure;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\Twig\MercureExtension;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;
use Teknoo\East\UXBundle\Components\AbstractWindow;

/**
 * Subscribes a Live window with a reference to its Mercure topic: adds the `window-reference` Stimulus controller to
 * its root element, with the URL of the hub. `mercure()` also grants the topics of all the windows in the authorization
 * cookie of the response, at each render, so a re-render renews it. Without the bundle of Mercure, nothing is added.
 *
 * The attributes are added here and not in the templates: an application overriding a template keeps the feature, and
 * a template can not call `mercure()`, the function does not exist without the bundle.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
final class WindowReferenceAttributesSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?MercureExtension $mercure = null,
    ) {
    }

    public function onPreRender(PreRenderEvent $event): void
    {
        $component = $event->getComponent();

        if (
            null === $this->mercure
            || !$component instanceof AbstractWindow
            || null === $component->windowReference
            || !$event->getMetadata()->get('live', false)
        ) {
            return;
        }

        $variables = $event->getVariables();
        $attributesKey = $event->getMetadata()->getAttributesVar();
        $attributes = $variables[$attributesKey] ?? null;

        if (!$attributes instanceof ComponentAttributes) {
            return;
        }

        $variables[$attributesKey] = $attributes->defaults([
            'data-controller' => 'window-reference',
            'data-window-reference-url-value' => $this->mercure->mercure(
                WindowMethodPublisher::TOPIC_PREFIX . $component->windowReference->id,
                ['subscribe' => ['urlpattern' => [WindowMethodPublisher::TOPIC_PATTERN]]],
            ),
        ]);

        $event->setVariables($variables);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreRenderEvent::class => 'onPreRender'];
    }
}
