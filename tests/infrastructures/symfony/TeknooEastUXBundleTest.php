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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Teknoo\East\UXBundle\DependencyInjection\TeknooEastUXExtension;
use Teknoo\East\UXBundle\TeknooEastUXBundle;

use function dirname;
use function realpath;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(TeknooEastUXBundle::class)]
class TeknooEastUXBundleTest extends TestCase
{
    private function buildBundle(): TeknooEastUXBundle
    {
        return new TeknooEastUXBundle();
    }

    public function testGetName(): void
    {
        $this->assertEquals('TeknooEastUXBundle', $this->buildBundle()->getName());
    }

    public function testGetContainerExtension(): void
    {
        $extension = $this->buildBundle()->getContainerExtension();

        $this->assertInstanceOf(TeknooEastUXExtension::class, $extension);
        $this->assertEquals('teknoo_east_ux', $extension->getAlias());
    }

    public function testGetPath(): void
    {
        // Symfony registers the Twig namespace `@TeknooEastUX` from `<path>/templates` and the translations from
        // `<path>/translations`
        $this->assertEquals(
            realpath(dirname(__DIR__, 3) . '/infrastructures/symfony'),
            realpath($this->buildBundle()->getPath()),
        );
    }
}
