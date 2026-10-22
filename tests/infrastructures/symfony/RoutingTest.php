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

namespace Teknoo\Tests\East\UXBundle;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

use function dirname;

/**
 * Checks the routes file of the bundle, `infrastructures/symfony/config/routes.yaml`.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversNothing]
class RoutingTest extends TestCase
{
    private function loadRoutes(): RouteCollection
    {
        $loader = new YamlFileLoader(new FileLocator(dirname(__DIR__, 3) . '/infrastructures/symfony/config'));

        return $loader->load('routes.yaml');
    }

    public function testComponentRendererRoute(): void
    {
        $routes = $this->loadRoutes();

        $this->assertCount(1, $routes);

        $route = $routes->get('_teknoo_east_ux_component_render');
        $this->assertNotNull($route);
        $this->assertEquals('/render', $route->getPath());
        $this->assertEquals(['POST'], $route->getMethods());
        $this->assertEquals([], $route->getSchemes());
        $this->assertEquals(
            'Teknoo\\East\\UXBundle\\Controller\\ComponentRenderer',
            $route->getDefault('_controller'),
        );
    }

    public function testComponentRendererRouteWithPrefix(): void
    {
        $routes = $this->loadRoutes();
        $routes->addPrefix('/component');

        $this->assertEquals('/component/render', $routes->get('_teknoo_east_ux_component_render')?->getPath());
    }
}
