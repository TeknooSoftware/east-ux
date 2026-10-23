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

namespace Teknoo\Tests\East\UXBundle\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Contracts\Theme\ThemePreferenceStorageInterface;
use Teknoo\East\UX\Object\DTO\Theme\ThemePreference;
use Teknoo\East\UX\Service\ThemeService;
use Teknoo\East\UXBundle\Controller\ThemePreferenceUpdater;
use Teknoo\Recipe\Promise\PromiseInterface;

use function json_encode;

/**
 * Controller receiving the theme chosen by the visitor.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ThemePreferenceUpdater::class)]
class ThemePreferenceUpdaterTest extends TestCase
{
    /**
     * @var list<ThemePreference>
     */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->saved = [];
    }

    /**
     * Theme service with `$current` as stored preference, keeping the saved preferences in `$this->saved`
     */
    private function buildController(
        ThemePreference $current = new ThemePreference(Style::Legacy, Mode::Auto),
    ): ThemePreferenceUpdater {
        $storage = $this->createStub(ThemePreferenceStorageInterface::class);
        $storage->method('load')->willReturnCallback(
            static function (PromiseInterface $promise) use ($current, $storage): ThemePreferenceStorageInterface {
                $promise->success($current);

                return $storage;
            },
        );
        $storage->method('save')->willReturnCallback(
            function (ThemePreference $preference) use ($storage): ThemePreferenceStorageInterface {
                $this->saved[] = $preference;

                return $storage;
            },
        );

        return new ThemePreferenceUpdater(new ThemeService(new ThemePreference(), $storage));
    }

    private function buildRequest(string $content, string $contentType = 'application/json'): Request
    {
        return Request::create(
            uri: '/east-ux/theme',
            method: 'POST',
            server: ['CONTENT_TYPE' => $contentType],
            content: $content,
        );
    }

    public function testIsASymfonyController(): void
    {
        // East Foundation's router hands the route back to Symfony only for such a controller
        $this->assertInstanceOf(AbstractController::class, $this->buildController());
    }

    public function testUpdateTheStyleAndTheMode(): void
    {
        $response = $this->buildController()(
            $this->buildRequest((string) json_encode(['style' => 'modern', 'mode' => 'dark'])),
        );

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertEquals([new ThemePreference(Style::Modern, Mode::Dark)], $this->saved);
    }

    public function testUpdateOnlyTheStyle(): void
    {
        $response = $this->buildController(new ThemePreference(Style::Legacy, Mode::Dark))(
            $this->buildRequest((string) json_encode(['style' => 'modern'])),
        );

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertEquals([new ThemePreference(Style::Modern, Mode::Dark)], $this->saved);
    }

    public function testUpdateOnlyTheMode(): void
    {
        $response = $this->buildController(new ThemePreference(Style::Modern, Mode::Dark))(
            $this->buildRequest((string) json_encode(['mode' => 'light'])),
        );

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertEquals([new ThemePreference(Style::Modern, Mode::Light)], $this->saved);
    }

    public function testRefuseANonJsonBody(): void
    {
        foreach (['text/plain', 'application/x-www-form-urlencoded', 'multipart/form-data'] as $contentType) {
            $response = $this->buildController()(
                $this->buildRequest((string) json_encode(['style' => 'modern']), $contentType),
            );

            $this->assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode(), $contentType);
        }

        $request = $this->buildRequest((string) json_encode(['style' => 'modern']));
        $request->headers->remove('CONTENT_TYPE');
        $this->assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $this->buildController()($request)->getStatusCode());

        $this->assertSame([], $this->saved);
    }

    public function testRefuseAnInvalidBody(): void
    {
        foreach (['', 'not json', '"modern"', '[]', '{}', '{"theme": "modern"}'] as $content) {
            $response = $this->buildController()($this->buildRequest($content));

            $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $content);
            $this->assertSame('Missing style or mode', $response->getContent());
        }

        $this->assertSame([], $this->saved);
    }

    public function testRefuseAnInvalidStyle(): void
    {
        foreach (['{"style": "retro"}', '{"style": null}', '{"style": 1, "mode": "dark"}'] as $content) {
            $response = $this->buildController()($this->buildRequest($content));

            $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $content);
            $this->assertSame('Invalid style', $response->getContent());
        }

        $this->assertSame([], $this->saved);
    }

    public function testRefuseAnInvalidMode(): void
    {
        foreach (['{"mode": "sepia"}', '{"mode": ["dark"]}', '{"style": "modern", "mode": "night"}'] as $content) {
            $response = $this->buildController()($this->buildRequest($content));

            $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $content);
            $this->assertSame('Invalid mode', $response->getContent());
        }

        $this->assertSame([], $this->saved);
    }
}
