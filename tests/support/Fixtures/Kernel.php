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

namespace Teknoo\Tests\East\UX\Support\Fixtures;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Teknoo\DI\SymfonyBridge\DIBridgeBundle;
use Teknoo\East\CommonBundle\TeknooEastCommonBundle;
use Teknoo\East\FoundationBundle\EastFoundationBundle;
use Teknoo\East\UXBundle\TeknooEastUXBundle;
use Teknoo\Tests\East\UX\Support\Fixtures\DependencyInjection\RemoveEastCommonCommandsPass;

use function dirname;

/**
 * Minimal Symfony application used by the kernel test and by Behat: East Foundation and East Common (with PHP-DI),
 * Symfony UX (Twig and Live Components, StimulusBundle), AssetMapper, SecurityBundle (HTTP basic authentication on a
 * stateless firewall, with in-memory users) and East UX.
 *
 * Its pages are rendered by East Common's recipes (list, creation and edition of `Item` objects, kept in memory, and a
 * static page with the menu bar). The component renderer is imported with the prefix `/component` and the Live
 * Component route with the prefix `/_components`, both behind authentication as an application must do.
 *
 * The configuration is in `config/`, the templates in `templates/` (the Twig default path), the compiled container in
 * `tests/var/cache/` and the logs in `tests/var/logs/`.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment = 'test', bool $debug = true)
    {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        yield new DIBridgeBundle();
        yield new EastFoundationBundle();
        yield new TeknooEastCommonBundle();
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new SecurityBundle();
        yield new TwigComponentBundle();
        yield new StimulusBundle();
        yield new LiveComponentBundle();
        yield new TeknooEastUXBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return dirname(__DIR__, 2) . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return dirname(__DIR__, 2) . '/var/logs';
    }

    public function getFixturesDir(): string
    {
        return __DIR__;
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveEastCommonCommandsPass());
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config/packages/*.yaml');
        $container->import(__DIR__ . '/config/services.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/config/routes/*.yaml');
    }
}
