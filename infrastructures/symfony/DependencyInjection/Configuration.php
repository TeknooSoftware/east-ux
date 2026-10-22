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

namespace Teknoo\East\UXBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration of the bundle, under the `teknoo_east_ux` key:
 * - `home_route`: route of the brand link of the `MenuBar` component (none by default),
 * - `form_theme`: Twig form theme applied by the `FormWindow` component when its `formTheme` prop is not set
 *   (none by default: the form themes of the application apply),
 * - `component_rendering.allow_unreferenced_components`: allows the component renderer to render a component without
 *   validator of its props (false by default).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class Configuration implements ConfigurationInterface
{
    public const string ROOT_NAME = 'teknoo_east_ux';

    /**
     * @return TreeBuilder<'array'>
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_NAME);

        $treeBuilder->getRootNode()
            ->children()
                ->stringNode('home_route')
                    ->info('Route of the brand link of the MenuBar component')
                    ->defaultNull()
                    ->cannotBeEmpty()
                ->end()
                ->stringNode('form_theme')
                    ->info('Twig form theme of the FormWindow component when its formTheme prop is not set')
                    ->defaultNull()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('component_rendering')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('allow_unreferenced_components')
                            ->info(
                                'Allows the component renderer to render a component without validator of its props '
                                . '(tag teknoo.east.ux.component_rendering.validation)'
                            )
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
