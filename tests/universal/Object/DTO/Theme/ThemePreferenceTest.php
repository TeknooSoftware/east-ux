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

namespace Teknoo\Tests\East\UX\Object\DTO\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;

/**
 * Data transfer object for the theme preference of a visitor.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ThemePreference::class)]
class ThemePreferenceTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $preference = new ThemePreference();

        $this->assertSame(Style::Legacy, $preference->style);
        $this->assertSame(Mode::Auto, $preference->mode);
    }

    public function testConstruct(): void
    {
        $preference = new ThemePreference(Style::Modern, Mode::Dark);

        $this->assertSame(Style::Modern, $preference->style);
        $this->assertSame(Mode::Dark, $preference->mode);
    }

    public function testWithReturnsACopy(): void
    {
        $preference = new ThemePreference(Style::Legacy, Mode::Light);

        $changed = $preference->with(style: Style::Modern, mode: Mode::Dark);

        $this->assertNotSame($preference, $changed);
        $this->assertSame(Style::Modern, $changed->style);
        $this->assertSame(Mode::Dark, $changed->mode);
        // The original preference is not changed
        $this->assertSame(Style::Legacy, $preference->style);
        $this->assertSame(Mode::Light, $preference->mode);
    }

    public function testWithKeepsTheValuesNotGiven(): void
    {
        $preference = new ThemePreference(Style::Modern, Mode::Light);

        $this->assertSame(Style::Modern, $preference->with(mode: Mode::Auto)->style);
        $this->assertSame(Mode::Auto, $preference->with(mode: Mode::Auto)->mode);
        $this->assertSame(Style::Legacy, $preference->with(style: Style::Legacy)->style);
        $this->assertSame(Mode::Light, $preference->with(style: Style::Legacy)->mode);
        $this->assertEquals($preference, $preference->with());
    }
}
