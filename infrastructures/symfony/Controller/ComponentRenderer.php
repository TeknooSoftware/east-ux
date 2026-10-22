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

namespace Teknoo\East\UXBundle\Controller;

use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\TwigComponent\ComponentFactory;
use Teknoo\East\CommonBundle\Contracts\Rendering\ComponentFactoryInterface;
use Teknoo\East\UXBundle\ComponentRendering\Exception\InvalidPropsException;
use Teknoo\East\UXBundle\ComponentRendering\PropsValidatorRegistry;
use Teknoo\Recipe\Promise\Promise;
use Throwable;

use function is_array;
use function is_string;

/**
 * Controller handling component renderer HTTP requests.
 *
 * The props are validated by the `PropsValidatorRegistry` before the rendering, with the validator tagged
 * `teknoo.east.ux.component_rendering.validation` for the class of the component (an anonymous or an unknown component
 * is looked up by its name): invalid props are refused with a 400 (`InvalidPropsException`), a component without
 * validator with a 403 (`UnreferencedComponentException`) unless the option
 * `teknoo_east_ux.component_rendering.allow_unreferenced_components` allows it. Props that are not an object are
 * refused with a 400.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ComponentRenderer extends AbstractController
{
    public function __construct(
        private readonly PropsValidatorRegistry $propsValidatorRegistry,
        private readonly ComponentFactory|ComponentFactoryInterface $componentFactory,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !isset($data['component']) || !isset($data['props'])) {
            return new Response('Missing component or props', 400);
        }

        if (!is_array($data['props'])) {
            throw new InvalidPropsException('The props must be an object');
        }

        $componentName = is_string($data['component']) ? $data['component'] : '';
        try {
            $metadata = $this->componentFactory->metadataFor($componentName);
            $component = $metadata->isAnonymous() ? $componentName : $metadata->getClass();
        } catch (InvalidArgumentException) {
            $component = $componentName;
        }

        /** @var Promise<array<mixed>, mixed, mixed> $promise */
        $promise = new Promise(
            null,
            static function (Throwable $error): never {
                throw $error;
            },
        );

        $this->propsValidatorRegistry->validate($component, $data['props'], $promise);

        return $this->render('@TeknooEastUX/component_renderer.html.twig', [
            'componentName' => $data['component'],
            'props' => $data['props'],
        ]);
    }
}
