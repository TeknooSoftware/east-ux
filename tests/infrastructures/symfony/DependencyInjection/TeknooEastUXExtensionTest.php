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

namespace Teknoo\Tests\East\UXBundle\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\TwigComponent\DependencyInjection\TwigComponentExtension;
use Teknoo\East\UXBundle\DependencyInjection\TeknooEastUXExtension;
use TypeError;

use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(TeknooEastUXExtension::class)]
class TeknooEastUXExtensionTest extends TestCase
{
    private const array COMPONENTS = [
        'Card',
        'ConfirmModal',
        'FormWindow',
        'ListWindow',
        'LoginWindow',
        'MenuBar',
        'ObjectList',
        'Pagination',
        'Window',
    ];

    /**
     * Fake FrameworkBundle directories (with or without the AssetMapper configuration of Symfony < 8.2)
     */
    private ?string $fakeFrameworkBundlePath = null;

    protected function tearDown(): void
    {
        if (null !== $this->fakeFrameworkBundlePath && is_dir($this->fakeFrameworkBundlePath)) {
            $configFile = $this->fakeFrameworkBundlePath . '/Resources/config/asset_mapper.php';
            if (is_file($configFile)) {
                unlink($configFile);
            }

            foreach (['/Resources/config', '/Resources', ''] as $subDirectory) {
                if (is_dir($this->fakeFrameworkBundlePath . $subDirectory)) {
                    rmdir($this->fakeFrameworkBundlePath . $subDirectory);
                }
            }
        }

        $this->fakeFrameworkBundlePath = null;

        parent::tearDown();
    }

    private function buildExtension(): TeknooEastUXExtension
    {
        return new TeknooEastUXExtension();
    }

    private function getFakeFrameworkBundlePath(bool $withAssetMapperConfiguration): string
    {
        $this->fakeFrameworkBundlePath = sys_get_temp_dir() . '/' . uniqid('east-ux-framework-bundle-', true);
        mkdir($this->fakeFrameworkBundlePath . '/Resources/config', 0777, true);

        if ($withAssetMapperConfiguration) {
            file_put_contents($this->fakeFrameworkBundlePath . '/Resources/config/asset_mapper.php', '<?php');
        }

        return $this->fakeFrameworkBundlePath;
    }

    /**
     * @param array<string, mixed> $bundlesMetadata
     */
    private function buildContainer(array $bundlesMetadata = [], bool $withTwigComponent = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', $bundlesMetadata);

        if ($withTwigComponent) {
            $container->registerExtension(new TwigComponentExtension());
        }

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    private function getExpectedAssetMapperConfig(): array
    {
        return [
            'asset_mapper' => [
                'paths' => [
                    dirname(__DIR__, 4) . '/assets/dist' => '@teknoo/east-ux',
                ],
            ],
        ];
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('teknoo_east_ux', $this->buildExtension()->getAlias());
    }

    public function testGetAssetsPath(): void
    {
        $this->assertEquals(
            dirname(__DIR__, 4) . '/assets/dist',
            $this->buildExtension()->getAssetsPath(),
        );
    }

    public function testPrependTwigComponentDefaults(): void
    {
        $container = $this->buildContainer();
        $this->buildExtension()->prepend($container);

        $this->assertEquals(
            [
                [
                    'defaults' => [
                        'Teknoo\\East\\UXBundle\\Components\\' => '@TeknooEastUX/components/',
                    ],
                ],
            ],
            $container->getExtensionConfig('twig_component'),
        );
    }

    public function testPrependTwigComponentDefaultsKeepsApplicationDefaults(): void
    {
        $container = $this->buildContainer();
        $container->loadFromExtension(
            'twig_component',
            [
                'anonymous_template_directory' => 'components/anonymous',
                'defaults' => ['App\\Twig\\Components\\' => 'components/'],
            ],
        );

        $this->buildExtension()->prepend($container);

        $configs = $container->getExtensionConfig('twig_component');
        $this->assertCount(2, $configs);
        $this->assertEquals(
            ['Teknoo\\East\\UXBundle\\Components\\' => '@TeknooEastUX/components/'],
            $configs[0]['defaults'],
        );
        $this->assertEquals(['App\\Twig\\Components\\' => 'components/'], $configs[1]['defaults']);
    }

    public function testPrependWithoutTwigComponentBundle(): void
    {
        $container = $this->buildContainer(withTwigComponent: false);
        $this->buildExtension()->prepend($container);

        $this->assertEquals([], $container->getExtensionConfig('twig_component'));
    }

    public function testPrependWithAssetMapperProvidedByFrameworkBundle(): void
    {
        // Symfony < 8.2: FrameworkBundle ships the AssetMapper configuration
        $container = $this->buildContainer([
            'FrameworkBundle' => [
                'path' => $this->getFakeFrameworkBundlePath(true),
                'namespace' => 'Symfony\\Bundle\\FrameworkBundle',
            ],
        ]);

        $this->buildExtension()->prepend($container);

        $this->assertEquals(
            [$this->getExpectedAssetMapperConfig()],
            $container->getExtensionConfig('framework'),
        );
    }

    public function testPrependWithAssetMapperBundle(): void
    {
        // Symfony >= 8.2: AssetMapperBundle provides the AssetMapper configuration
        $container = $this->buildContainer([
            'FrameworkBundle' => [
                'path' => $this->getFakeFrameworkBundlePath(false),
                'namespace' => 'Symfony\\Bundle\\FrameworkBundle',
            ],
            'AssetMapperBundle' => [
                'path' => '/foo/asset-mapper',
                'namespace' => 'Symfony\\Bundle\\AssetMapperBundle',
            ],
        ]);

        $this->buildExtension()->prepend($container);

        $this->assertEquals(
            [$this->getExpectedAssetMapperConfig()],
            $container->getExtensionConfig('framework'),
        );
    }

    public function testPrependWithoutAssetMapperConfiguration(): void
    {
        // Symfony >= 8.2 without AssetMapperBundle
        $container = $this->buildContainer([
            'FrameworkBundle' => [
                'path' => $this->getFakeFrameworkBundlePath(false),
                'namespace' => 'Symfony\\Bundle\\FrameworkBundle',
            ],
        ]);

        $this->buildExtension()->prepend($container);

        $this->assertEquals([], $container->getExtensionConfig('framework'));
    }

    public function testPrependWithoutAssetMapperInstalled(): void
    {
        // symfony/asset-mapper not installed: nothing is prepended, even with an AssetMapper configuration available
        $container = $this->buildContainer([
            'FrameworkBundle' => [
                'path' => $this->getFakeFrameworkBundlePath(true),
                'namespace' => 'Symfony\\Bundle\\FrameworkBundle',
            ],
            'AssetMapperBundle' => [
                'path' => '/foo/asset-mapper',
                'namespace' => 'Symfony\\Bundle\\AssetMapperBundle',
            ],
        ]);

        $extension = new class () extends TeknooEastUXExtension {
            protected function isAssetMapperInstalled(): bool
            {
                return false;
            }
        };

        $extension->prepend($container);

        $this->assertEquals([], $container->getExtensionConfig('framework'));
        $this->assertEquals(
            [
                [
                    'defaults' => [
                        'Teknoo\\East\\UXBundle\\Components\\' => '@TeknooEastUX/components/',
                    ],
                ],
            ],
            $container->getExtensionConfig('twig_component'),
        );
    }

    public function testPrependWithoutFrameworkBundle(): void
    {
        $container = $this->buildContainer([
            'AssetMapperBundle' => [
                'path' => '/foo/asset-mapper',
                'namespace' => 'Symfony\\Bundle\\AssetMapperBundle',
            ],
        ]);

        $this->buildExtension()->prepend($container);

        $this->assertEquals([], $container->getExtensionConfig('framework'));
    }

    public function testPrependWithInvalidFrameworkBundleMetadata(): void
    {
        $container = $this->buildContainer([
            'FrameworkBundle' => [
                'namespace' => 'Symfony\\Bundle\\FrameworkBundle',
            ],
        ]);

        $this->buildExtension()->prepend($container);

        $this->assertEquals([], $container->getExtensionConfig('framework'));
    }

    public function testPrependWithInvalidBundlesMetadata(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', 'foo');

        $this->buildExtension()->prepend($container);

        $this->assertEquals([], $container->getExtensionConfig('framework'));
    }

    public function testLoadWithDefaultConfiguration(): void
    {
        $container = new ContainerBuilder();
        $this->buildExtension()->load([], $container);

        $this->assertNull($container->getParameter('teknoo_east_ux.home_route'));
        $this->assertNull($container->getParameter('teknoo_east_ux.form_theme'));
        $this->assertFalse(
            $container->getParameter('teknoo_east_ux.component_rendering.allow_unreferenced_components')
        );
    }

    public function testLoadWithMergedConfigurations(): void
    {
        $container = new ContainerBuilder();
        $this->buildExtension()->load(
            [
                [
                    'home_route' => 'first_home',
                ],
                [
                    'home_route' => 'app_home',
                    'form_theme' => '@App/fields.html.twig',
                    'component_rendering' => ['allow_unreferenced_components' => true],
                ],
            ],
            $container,
        );

        $this->assertEquals('app_home', $container->getParameter('teknoo_east_ux.home_route'));
        $this->assertEquals('@App/fields.html.twig', $container->getParameter('teknoo_east_ux.form_theme'));
        $this->assertTrue(
            $container->getParameter('teknoo_east_ux.component_rendering.allow_unreferenced_components')
        );
    }

    public function testLoadWithInvalidConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->buildExtension()->load([['home_route' => '']], new ContainerBuilder());
    }

    public function testLoadRegistersTheMenuService(): void
    {
        $container = new ContainerBuilder();
        $this->buildExtension()->load([], $container);

        $definition = $container->getDefinition('Teknoo\\East\\UX\\Service\\MenuService');
        $this->assertEquals('Teknoo\\East\\UX\\Service\\MenuService', $definition->getClass());
        $this->assertFalse($definition->isAutoconfigured());
        $this->assertEquals([['method' => 'reset']], $definition->getTag('kernel.reset'));

        $argument = $definition->getArgument(0);
        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertEquals('teknoo.east.ux.menu.generator', $argument->getTag());
    }

    public function testLoadRegistersTheComponents(): void
    {
        $container = new ContainerBuilder();
        $this->buildExtension()->load([], $container);

        foreach (self::COMPONENTS as $component) {
            $definition = $container->getDefinition('Teknoo\\East\\UXBundle\\Components\\' . $component);
            $this->assertEquals('Teknoo\\East\\UXBundle\\Components\\' . $component, $definition->getClass());
            $this->assertTrue($definition->isAutoconfigured(), $component);
            $this->assertFalse($definition->isAutowired(), $component);
            $this->assertFalse($definition->isPublic(), $component);
        }

        $this->assertFalse($container->hasDefinition('Teknoo\\East\\UXBundle\\Components\\AbstractWindow'));
    }

    public function testLoadInjectsTheConfigurationInTheComponents(): void
    {
        $container = new ContainerBuilder();
        $this->buildExtension()->load([], $container);

        $formWindow = $container->getDefinition('Teknoo\\East\\UXBundle\\Components\\FormWindow');
        $this->assertEquals(
            [new Reference('form.factory'), '%teknoo_east_ux.form_theme%'],
            $formWindow->getArguments(),
        );

        $menuBar = $container->getDefinition('Teknoo\\East\\UXBundle\\Components\\MenuBar');
        $this->assertEquals(
            [new Reference('Teknoo\\East\\UX\\Service\\MenuService'), '%teknoo_east_ux.home_route%'],
            $menuBar->getArguments(),
        );

        $listWindow = $container->getDefinition('Teknoo\\East\\UXBundle\\Components\\ListWindow');
        $this->assertEquals(
            [new Reference('request_stack'), new Reference('form.factory')],
            $listWindow->getArguments(),
        );

        $loginWindow = $container->getDefinition('Teknoo\\East\\UXBundle\\Components\\LoginWindow');
        $this->assertEquals(
            [new Reference('security.authentication_utils')],
            $loginWindow->getArguments(),
        );

        $pagination = $container->getDefinition('Teknoo\\East\\UXBundle\\Components\\Pagination');
        $this->assertEquals([new Reference('request_stack')], $pagination->getArguments());
    }

    public function testLoadRegistersTheComponentRenderer(): void
    {
        $container = new ContainerBuilder();
        $this->buildExtension()->load([], $container);

        $definition = $container->getDefinition('Teknoo\\East\\UXBundle\\Controller\\ComponentRenderer');
        $this->assertEquals('Teknoo\\East\\UXBundle\\Controller\\ComponentRenderer', $definition->getClass());
        $this->assertTrue($definition->isPublic());
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->isAutoconfigured());
        $this->assertEquals(
            [
                new Reference('Teknoo\\East\\UXBundle\\ComponentRendering\\PropsValidatorRegistry'),
                new Reference('ux.twig_component.component_factory'),
            ],
            $definition->getArguments(),
        );
    }

    public function testLoadRegistersTheValidationOfTheProps(): void
    {
        $container = new ContainerBuilder();
        $this->buildExtension()->load([], $container);

        $registry = $container->getDefinition('Teknoo\\East\\UXBundle\\ComponentRendering\\PropsValidatorRegistry');
        $argument = $registry->getArgument(0);
        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertEquals('teknoo.east.ux.component_rendering.validation', $argument->getTag());
        $this->assertEquals('component', $argument->getIndexAttribute());
        $this->assertEquals(
            '%teknoo_east_ux.component_rendering.allow_unreferenced_components%',
            $registry->getArgument(1),
        );

        $validator = $container->getDefinition(
            'Teknoo\\East\\UXBundle\\ComponentRendering\\Validator\\FormWindowPropsValidator'
        );
        $this->assertEquals([new Reference('validator')], $validator->getArguments());
        $this->assertEquals(
            [['component' => 'Teknoo\\East\\UXBundle\\Components\\FormWindow']],
            $validator->getTag('teknoo.east.ux.component_rendering.validation'),
        );
    }

    public function testLoadErrorContainer(): void
    {
        $this->expectException(TypeError::class);
        /** @phpstan-ignore argument.type */
        $this->buildExtension()->load([], new stdClass());
    }

    public function testPrependErrorContainer(): void
    {
        $this->expectException(TypeError::class);
        /** @phpstan-ignore argument.type */
        $this->buildExtension()->prepend(new stdClass());
    }
}
