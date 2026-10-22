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

use DI\Container;
use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use Teknoo\East\Foundation\Template\EngineInterface;
use Teknoo\East\UX\Service\MenuService;

use function dirname;

/**
 * Checks the PHP-DI definitions files of the bundle, `infrastructures/symfony/config/di.php` and `twig.php`, that
 * an application lists in `di_bridge.definitions`. Without coverage attribute, as East Common's container tests:
 * these files hold no class to cover, their lines are covered by this test.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ContainerTest extends TestCase
{
    private function buildContainer(string $file): Container
    {
        $containerDefinition = new ContainerBuilder();
        $containerDefinition->addDefinitions(dirname(__DIR__, 3) . '/infrastructures/symfony/config/' . $file);
        $containerDefinition->useAutowiring(false);

        return $containerDefinition->build();
    }

    public function testDiDefinitionsAreLoadable(): void
    {
        $container = $this->buildContainer('di.php');
        $this->assertInstanceOf(Container::class, $container);

        // PHP-DI reads a definitions file on the first lookup of an entry. The menu service is a Symfony service
        $this->assertFalse($container->has(MenuService::class));
    }

    public function testTwigDefinitionsAreLoadable(): void
    {
        $container = $this->buildContainer('twig.php');
        $this->assertInstanceOf(Container::class, $container);

        // PHP-DI reads a definitions file on the first lookup of an entry. The East Twig engine is East Common's
        $this->assertFalse($container->has(EngineInterface::class));
    }
}
