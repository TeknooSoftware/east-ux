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

namespace Teknoo\Tests\East\UX\Service;

use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Contracts\Theme\ThemePreferenceStorageInterface;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\East\UX\Service\ThemeService;
use Teknoo\Recipe\Promise\PromiseInterface;

/**
 * Unit test for the theme service class.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ThemeService::class)]
class ThemeServiceTest extends TestCase
{
    /**
     * Storage holding `$stored` (nothing when null), counting its loads and keeping the saved preferences
     */
    private function buildStorage(?ThemePreference $stored = null): ThemePreferenceStorageInterface
    {
        return new class ($stored) implements ThemePreferenceStorageInterface {
            public int $loads = 0;

            /**
             * @var list<ThemePreference>
             */
            public array $saved = [];

            public function __construct(
                private readonly ?ThemePreference $stored,
            ) {
            }

            public function load(PromiseInterface $promise): ThemePreferenceStorageInterface
            {
                ++$this->loads;

                if (null === $this->stored) {
                    $promise->fail(new DomainException('Nothing stored'));
                } else {
                    $promise->success($this->stored);
                }

                return $this;
            }

            public function save(ThemePreference $preference): ThemePreferenceStorageInterface
            {
                $this->saved[] = $preference;

                return $this;
            }
        };
    }

    public function testImplementsResetInterface(): void
    {
        $this->assertInstanceOf(ResetInterface::class, new ThemeService());
    }

    public function testDefaultPreference(): void
    {
        $service = new ThemeService();

        $this->assertEquals(new ThemePreference(Style::Legacy, Mode::Auto), $service->getDefault());
        $this->assertSame($service->getDefault(), $service->getPreference());
    }

    public function testPreferenceWithoutStorageIsTheDefaultOne(): void
    {
        $default = new ThemePreference(Style::Modern, Mode::Dark);

        $this->assertSame($default, new ThemeService($default)->getPreference());
    }

    public function testPreferenceWithoutStoredValueIsTheDefaultOne(): void
    {
        $default = new ThemePreference(Style::Modern, Mode::Light);
        $storage = $this->buildStorage();

        $this->assertSame($default, new ThemeService($default, $storage)->getPreference());
        $this->assertSame(1, $storage->loads);
    }

    public function testPreferenceIsReadFromTheStorageOnce(): void
    {
        $stored = new ThemePreference(Style::Modern, Mode::Dark);
        $storage = $this->buildStorage($stored);
        $service = new ThemeService(new ThemePreference(), $storage);

        $this->assertSame($stored, $service->getPreference());
        $this->assertSame($stored, $service->getPreference());
        $this->assertSame(1, $storage->loads);
    }

    public function testResetReadsTheStorageAgain(): void
    {
        $storage = $this->buildStorage(new ThemePreference(Style::Modern, Mode::Dark));
        $service = new ThemeService(new ThemePreference(), $storage);

        $service->getPreference();
        $service->reset();
        $service->getPreference();

        $this->assertSame(2, $storage->loads);
    }

    public function testUpdateSavesThePreference(): void
    {
        $storage = $this->buildStorage();
        $service = new ThemeService(new ThemePreference(), $storage);
        $preference = new ThemePreference(Style::Modern, Mode::Light);

        $this->assertSame($service, $service->update($preference));
        $this->assertSame([$preference], $storage->saved);
        // The updated preference is the current one, without reading the storage
        $this->assertSame($preference, $service->getPreference());
        $this->assertSame(0, $storage->loads);
    }

    public function testUpdateWithoutStorage(): void
    {
        $service = new ThemeService();
        $preference = new ThemePreference(Style::Modern, Mode::Dark);

        $this->assertSame($preference, $service->update($preference)->getPreference());

        $service->reset();
        $this->assertSame($service->getDefault(), $service->getPreference());
    }

    /**
     * @return iterable<string, array{Style, Mode, string, string|null}>
     */
    public static function renderingProvider(): iterable
    {
        yield 'legacy light' => [Style::Legacy, Mode::Light, '', null];
        yield 'legacy dark' => [Style::Legacy, Mode::Dark, '', 'dark'];
        yield 'legacy auto' => [Style::Legacy, Mode::Auto, '', null];
        yield 'modern light' => [Style::Modern, Mode::Light, 'system-modern', 'light'];
        yield 'modern dark' => [Style::Modern, Mode::Dark, 'system-modern', 'dark'];
        yield 'modern auto' => [Style::Modern, Mode::Auto, 'system-modern', 'light'];
    }

    #[DataProvider('renderingProvider')]
    public function testRenderingOnHtml(Style $style, Mode $mode, string $class, ?string $bootstrapTheme): void
    {
        $service = new ThemeService();
        $preference = new ThemePreference($style, $mode);

        $this->assertSame($class, $service->getHtmlClass($preference));
        $this->assertSame($bootstrapTheme, $service->getBootstrapTheme($preference));
    }
}
