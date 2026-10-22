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

use Exception;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

use function dirname;
use function interface_exists;
use function is_array;
use function is_file;
use function is_string;

/**
 * Symfony extension of East UX:
 * - prepends `twig_component.defaults` so that the components of `Teknoo\East\UXBundle\Components\` keep their
 *   short names (`<twig:FormWindow>`, `component('FormWindow', ...)`), with their templates in
 *   `@TeknooEastUX/components/`,
 * - prepends `framework.asset_mapper.paths` with the `assets/dist` directory of the package, as
 *   `@teknoo/east-ux`, when AssetMapper is available (same guard as Symfony UX's own bundles),
 * - loads the services of the bundle and exposes its configuration as `teknoo_east_ux.*` parameters.
 *
 * The Twig namespace `@TeknooEastUX` (templates/) and the translations (translations/, domain `east_ux`) are
 * registered by Symfony from the bundle's directory.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class TeknooEastUXExtension extends Extension implements PrependExtensionInterface
{
    public const string COMPONENTS_NAMESPACE = 'Teknoo\\East\\UXBundle\\Components\\';

    public const string COMPONENTS_TEMPLATE_DIRECTORY = '@TeknooEastUX/components/';

    public const string ASSETS_NAMESPACE = '@teknoo/east-ux';

    public function getAssetsPath(): string
    {
        return dirname(__DIR__, 3) . '/assets/dist';
    }

    /**
     * `symfony/asset-mapper` is an optional dependency of the bundle.
     */
    protected function isAssetMapperInstalled(): bool
    {
        return interface_exists(AssetMapperInterface::class);
    }

    private function isAssetMapperAvailable(ContainerBuilder $container): bool
    {
        if (!$this->isAssetMapperInstalled()) {
            return false;
        }

        // Before Symfony 8.2, FrameworkBundle provided the AssetMapper configuration.
        $bundlesMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!is_array($bundlesMetadata) || !is_array($bundlesMetadata['FrameworkBundle'] ?? null)) {
            return false;
        }

        if (isset($bundlesMetadata['AssetMapperBundle'])) {
            return true;
        }

        $frameworkBundlePath = $bundlesMetadata['FrameworkBundle']['path'] ?? null;

        return is_string($frameworkBundlePath)
            && is_file($frameworkBundlePath . '/Resources/config/asset_mapper.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig_component')) {
            $container->prependExtensionConfig(
                'twig_component',
                [
                    'defaults' => [
                        self::COMPONENTS_NAMESPACE => self::COMPONENTS_TEMPLATE_DIRECTORY,
                    ],
                ],
            );
        }

        if ($this->isAssetMapperAvailable($container)) {
            $container->prependExtensionConfig(
                'framework',
                [
                    'asset_mapper' => [
                        'paths' => [
                            $this->getAssetsPath() => self::ASSETS_NAMESPACE,
                        ],
                    ],
                ],
            );
        }
    }

    /**
     * @param array<mixed> $configs
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        /**
         * @var array{
         *     home_route: string|null,
         *     form_theme: string|null,
         *     component_rendering: array{allow_unreferenced_components: bool},
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../config'));
        $loader->load('services.yaml');

        $container->setParameter('teknoo_east_ux.home_route', $config['home_route']);
        $container->setParameter('teknoo_east_ux.form_theme', $config['form_theme']);
        $container->setParameter(
            'teknoo_east_ux.component_rendering.allow_unreferenced_components',
            $config['component_rendering']['allow_unreferenced_components'],
        );
    }

    public function getAlias(): string
    {
        return Configuration::ROOT_NAME;
    }
}
