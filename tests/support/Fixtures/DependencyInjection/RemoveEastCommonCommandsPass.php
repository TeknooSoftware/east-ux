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

namespace Teknoo\Tests\East\UX\Support\Fixtures\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Teknoo\East\CommonBundle\Command\CreateUserCommand;

/**
 * Removes East Common's console commands from the test application, which does not use them: they are tagged without
 * a command name, so the console builds them eagerly when it lists its commands (to run `debug:asset-map`), and they
 * need services of East Common's Doctrine and minify infrastructures that this application does not load.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class RemoveEastCommonCommandsPass implements CompilerPassInterface
{
    /**
     * @var list<string>
     */
    public const array COMMANDS = [
        CreateUserCommand::class,
        'teknoo.east.command.symfony.command.minify_css',
        'teknoo.east.command.symfony.command.minify_js',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::COMMANDS as $serviceId) {
            if ($container->hasDefinition($serviceId)) {
                $container->removeDefinition($serviceId);
            }
        }
    }
}
