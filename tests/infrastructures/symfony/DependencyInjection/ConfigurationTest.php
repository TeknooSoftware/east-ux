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
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;
use Teknoo\East\UXBundle\DependencyInjection\Configuration;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    private function buildConfiguration(): Configuration
    {
        return new Configuration();
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     * @return array<mixed>
     */
    private function process(array $configs): array
    {
        return new Processor()->processConfiguration($this->buildConfiguration(), $configs);
    }

    public function testGetConfigTreeBuilder(): void
    {
        $treeBuilder = $this->buildConfiguration()->getConfigTreeBuilder();

        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
        $this->assertEquals('teknoo_east_ux', $treeBuilder->buildTree()->getName());
    }

    public function testDefaultValues(): void
    {
        $this->assertEquals(
            [
                'home_route' => null,
                'form_theme' => null,
                'component_rendering' => ['allow_unreferenced_components' => false],
            ],
            $this->process([]),
        );
    }

    public function testFullConfiguration(): void
    {
        $this->assertEquals(
            [
                'home_route' => 'app_home',
                'form_theme' => '@App/fields.html.twig',
                'component_rendering' => ['allow_unreferenced_components' => true],
            ],
            $this->process([
                [
                    'home_route' => 'app_home',
                    'form_theme' => '@App/fields.html.twig',
                    'component_rendering' => ['allow_unreferenced_components' => true],
                ],
            ]),
        );
    }

    public function testLastScalarValueWins(): void
    {
        $config = $this->process([
            ['home_route' => 'first_home', 'form_theme' => 'first.html.twig'],
            ['home_route' => 'second_home'],
        ]);

        $this->assertEquals('second_home', $config['home_route']);
        $this->assertEquals('first.html.twig', $config['form_theme']);
    }

    public function testEmptyHomeRouteIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([['home_route' => '']]);
    }

    public function testEmptyFormThemeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([['form_theme' => '']]);
    }

    public function testNonStringHomeRouteIsRejected(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->process([['home_route' => 123]]);
    }

    public function testNonBooleanAllowUnreferencedComponentsIsRejected(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->process([['component_rendering' => ['allow_unreferenced_components' => 'yes']]]);
    }

    public function testUnknownKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([['unknown_key' => 'foo']]);
    }
}
