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

namespace Teknoo\Tests\East\UXBundle\Theme;

use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Contracts\Theme\ThemePreferenceStorageInterface;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\East\UXBundle\Theme\SessionThemePreferenceStorage;
use Teknoo\Recipe\Promise\Promise;
use Throwable;

/**
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(SessionThemePreferenceStorage::class)]
class SessionThemePreferenceStorageTest extends TestCase
{
    private ?ThemePreference $loaded = null;

    private ?Throwable $error = null;

    protected function setUp(): void
    {
        $this->loaded = null;
        $this->error = null;
    }

    /**
     * A request with a session, started by a previous request when `$previous` (the session cookie is sent)
     */
    private function buildRequestStack(?Session $session = null, bool $previous = true): RequestStack
    {
        $requestStack = new RequestStack();

        $request = new Request();
        if (null !== $session) {
            $request->setSession($session);
            if ($previous) {
                $request->cookies->set($session->getName(), 'session-id');
            }
        }

        $requestStack->push($request);

        return $requestStack;
    }

    private function load(SessionThemePreferenceStorage $storage): void
    {
        /** @var Promise<ThemePreference, mixed, mixed> $promise */
        $promise = new Promise(
            function (ThemePreference $preference): void {
                $this->loaded = $preference;
            },
            function (Throwable $error): void {
                $this->error = $error;
            },
        );

        $this->assertSame($storage, $storage->load($promise));
    }

    public function testIsAThemePreferenceStorage(): void
    {
        $this->assertInstanceOf(
            ThemePreferenceStorageInterface::class,
            new SessionThemePreferenceStorage(new RequestStack()),
        );
    }

    public function testLoadWithoutRequest(): void
    {
        $this->load(new SessionThemePreferenceStorage(new RequestStack()));

        $this->assertNull($this->loaded);
        $this->assertInstanceOf(DomainException::class, $this->error);
    }

    public function testLoadWithoutSession(): void
    {
        $this->load(new SessionThemePreferenceStorage($this->buildRequestStack()));

        $this->assertNull($this->loaded);
        $this->assertInstanceOf(DomainException::class, $this->error);
    }

    public function testLoadDoesNotStartANewSession(): void
    {
        $session = new Session(new MockArraySessionStorage());

        $this->load(new SessionThemePreferenceStorage($this->buildRequestStack($session, false)));

        $this->assertNull($this->loaded);
        $this->assertInstanceOf(DomainException::class, $this->error);
        $this->assertFalse($session->isStarted());
    }

    public function testLoadWithoutStoredPreference(): void
    {
        $session = new Session(new MockArraySessionStorage());

        $this->load(new SessionThemePreferenceStorage($this->buildRequestStack($session)));

        $this->assertNull($this->loaded);
        $this->assertInstanceOf(DomainException::class, $this->error);
    }

    public function testLoadWithInvalidStoredPreference(): void
    {
        foreach (
            [
                'not an array',
                ['style' => 'modern'],
                ['style' => 'retro', 'mode' => 'dark'],
                ['style' => 'modern', 'mode' => 42],
            ] as $value
        ) {
            $this->loaded = null;
            $this->error = null;

            $session = new Session(new MockArraySessionStorage());
            $session->set(SessionThemePreferenceStorage::SESSION_KEY, $value);

            $this->load(new SessionThemePreferenceStorage($this->buildRequestStack($session)));

            $this->assertNull($this->loaded);
            $this->assertInstanceOf(DomainException::class, $this->error);
        }
    }

    public function testLoadTheStoredPreference(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('teknoo_east_ux_theme', ['style' => 'modern', 'mode' => 'dark']);

        $this->load(new SessionThemePreferenceStorage($this->buildRequestStack($session)));

        $this->assertNull($this->error);
        $this->assertEquals(new ThemePreference(Style::Modern, Mode::Dark), $this->loaded);
    }

    public function testSaveInTheSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $storage = new SessionThemePreferenceStorage($this->buildRequestStack($session, false));

        $this->assertSame($storage, $storage->save(new ThemePreference(Style::Legacy, Mode::Light)));
        $this->assertSame(['style' => 'legacy', 'mode' => 'light'], $session->get('teknoo_east_ux_theme'));
    }

    public function testSaveWithoutSession(): void
    {
        $this->expectException(SessionNotFoundException::class);

        new SessionThemePreferenceStorage($this->buildRequestStack())
            ->save(new ThemePreference(Style::Legacy, Mode::Light));
    }
}
