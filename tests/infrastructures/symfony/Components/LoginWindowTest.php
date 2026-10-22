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

namespace Teknoo\Tests\East\UXBundle\Components;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bridge\Twig\Extension\CsrfRuntime;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Twig\ComponentLexer;
use Teknoo\East\UXBundle\Components\AbstractWindow;
use Teknoo\East\UXBundle\Components\LoginWindow;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function dirname;
use function get_object_vars;
use function is_scalar;
use function property_exists;
use function strtr;

/**
 * Live Twig component rendering the login window interface.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(LoginWindow::class)]
class LoginWindowTest extends TestCase
{
    /**
     * View parameters set by East Common's rendering steps (`RenderList`, `RenderForm`, `RenderError`, `Render`) and
     * the form of the workplan: `LiveComponentBuilder` assigns each of them, by direct assignment, to the component
     * property of the same name.
     */
    private const array EAST_VIEW_PARAMETERS = [
        'objectsCollection',
        'page',
        'pageCount',
        'queryParams',
        'searchForm',
        'objectInstance',
        'request',
        'formView',
        'isTranslatable',
        'objectSaved',
        'error',
        'form',
        'objectViewKey',
    ];

    private AuthenticationUtils $authenticationUtils;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticationUtils = $this->createStub(AuthenticationUtils::class);
    }

    private function buildLoginWindow(): LoginWindow
    {
        return new LoginWindow($this->authenticationUtils);
    }

    private function buildTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            /**
             * @param array<string, mixed> $parameters
             */
            public function trans(
                string $id,
                array $parameters = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                $replacements = [];
                foreach ($parameters as $key => $value) {
                    $replacements[$key] = is_scalar($value) ? (string) $value : '';
                }

                return '[' . ($domain ?? 'messages') . ']' . strtr($id, $replacements);
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }

    private function render(LoginWindow $window): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 4) . '/infrastructures/symfony/templates', 'TeknooEastUX');

        $twig = new Environment($loader, ['strict_variables' => true, 'cache' => false]);
        $twig->setLexer(new ComponentLexer($twig));
        $twig->addExtension(new TranslationExtension($this->buildTranslator()));

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturnCallback(
            static fn (string $tokenId): CsrfToken => new CsrfToken($tokenId, 'token-for-' . $tokenId),
        );
        $twig->addExtension(new CsrfExtension());
        $twig->addRuntimeLoader(
            new FactoryRuntimeLoader(
                [CsrfRuntime::class => static fn (): CsrfRuntime => new CsrfRuntime($csrfTokenManager)],
            ),
        );

        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $escaper->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render(
            '@TeknooEastUX/components/LoginWindow.html.twig',
            [
                ...get_object_vars($window),
                'this' => $window,
                'attributes' => new ComponentAttributes([], $escaper),
            ],
        );
    }

    public function testConstruct(): void
    {
        $window = $this->buildLoginWindow();

        $this->assertInstanceOf(LoginWindow::class, $window);
        $this->assertInstanceOf(AbstractWindow::class, $window);
    }

    public function testIsATwigComponent(): void
    {
        $attributes = (new ReflectionClass(LoginWindow::class))->getAttributes(AsTwigComponent::class);

        // No explicit name nor template: they come from the `twig_component.defaults` entry of the bundle
        $this->assertCount(1, $attributes);
        $this->assertEquals([], $attributes[0]->getArguments());
    }

    public function testDefaultProperties(): void
    {
        $window = $this->buildLoginWindow();

        $this->assertEquals('', $window->actionPath);
        $this->assertTrue($window->rememberMe);
        $this->assertEquals('authenticate', $window->csrfTokenId);
    }

    public function testNoNonPublicPropertyIsNamedAsAnEastViewParameter(): void
    {
        $window = $this->buildLoginWindow();

        foreach (self::EAST_VIEW_PARAMETERS as $name) {
            $assignable = !property_exists($window, $name)
                || (($property = new ReflectionProperty($window, $name))->isPublic() && !$property->isReadOnly());

            $this->assertTrue($assignable, $name . ' must be a public and writable property or no property');
        }
    }

    public function testGetAuthenticationError(): void
    {
        $error = new AuthenticationException('Invalid credentials');
        $authUtils = $this->createStub(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn($error);

        $window = new LoginWindow($authUtils);

        $this->assertSame($error, $window->getAuthenticationError());
    }

    public function testGetAuthenticationErrorReturnsNull(): void
    {
        $authUtils = $this->createStub(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(null);

        $window = new LoginWindow($authUtils);

        $this->assertNull($window->getAuthenticationError());
    }

    public function testGetLastUsername(): void
    {
        $authUtils = $this->createStub(AuthenticationUtils::class);
        $authUtils->method('getLastUsername')->willReturn('testuser@example.com');

        $window = new LoginWindow($authUtils);

        $this->assertEquals('testuser@example.com', $window->getLastUsername());
    }

    public function testGetLastUsernameReturnsEmptyString(): void
    {
        $authUtils = $this->createStub(AuthenticationUtils::class);
        $authUtils->method('getLastUsername')->willReturn('');

        $window = new LoginWindow($authUtils);

        $this->assertEquals('', $window->getLastUsername());
    }

    public function testRenderDefault(): void
    {
        $authUtils = $this->createStub(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(null);
        $authUtils->method('getLastUsername')->willReturn('john@example.com');

        $window = new LoginWindow($authUtils);
        $window->title = 'Sign in';
        $window->actionPath = '/login/check';

        $output = $this->render($window);

        $this->assertStringContainsString('<h1 class="title">Sign in</h1>', $output);
        $this->assertStringContainsString('<form action="/login/check" method="post">', $output);
        $this->assertStringContainsString('value="john@example.com"', $output);
        $this->assertStringContainsString('name="_username"', $output);
        $this->assertStringContainsString('name="_password"', $output);
        $this->assertStringContainsString('[east_ux]teknoo.east.ux.login_window.input.username', $output);
        $this->assertStringContainsString('[east_ux]teknoo.east.ux.login_window.input.password', $output);
        $this->assertStringContainsString('[east_ux]teknoo.east.ux.login_window.button.sign_in', $output);
        $this->assertStringNotContainsString('login-error', $output);
    }

    public function testRenderRememberMe(): void
    {
        $window = $this->buildLoginWindow();

        $output = $this->render($window);

        $this->assertStringContainsString('name="_remember_me"', $output);
        $this->assertStringContainsString('[east_ux]teknoo.east.ux.login_window.input.remember_me', $output);
    }

    public function testRenderWithoutRememberMe(): void
    {
        $window = $this->buildLoginWindow();
        $window->rememberMe = false;

        $output = $this->render($window);

        $this->assertStringNotContainsString('_remember_me', $output);
        $this->assertStringNotContainsString('teknoo.east.ux.login_window.input.remember_me', $output);
    }

    public function testRenderCsrfTokenWithDefaultTokenId(): void
    {
        $window = $this->buildLoginWindow();

        $output = $this->render($window);

        $this->assertStringContainsString(
            '<input type="hidden" name="_csrf_token" value="token-for-authenticate">',
            $output,
        );
    }

    public function testRenderCsrfTokenWithCustomTokenId(): void
    {
        $window = $this->buildLoginWindow();
        $window->csrfTokenId = 'app_login';

        $output = $this->render($window);

        $this->assertStringContainsString(
            '<input type="hidden" name="_csrf_token" value="token-for-app_login">',
            $output,
        );
    }

    public function testRenderWithoutCsrfToken(): void
    {
        $window = $this->buildLoginWindow();
        $window->csrfTokenId = '';

        $output = $this->render($window);

        $this->assertStringNotContainsString('_csrf_token', $output);
    }

    public function testRenderAuthenticationErrorTranslatedInTheSecurityDomain(): void
    {
        $authUtils = $this->createStub(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(
            new CustomUserMessageAuthenticationException(
                'Too many attempts, retry in %minutes% minutes.',
                ['%minutes%' => 5],
            ),
        );
        $authUtils->method('getLastUsername')->willReturn('');

        $window = new LoginWindow($authUtils);

        $output = $this->render($window);

        $this->assertStringContainsString('<div id="login-error" class="alert alert-danger" role="alert">', $output);
        $this->assertStringContainsString(
            '<strong>[east_ux]teknoo.east.ux.login_window.text.error_prefix</strong>',
            $output,
        );
        $this->assertStringContainsString('[security]Too many attempts, retry in 5 minutes.', $output);
    }

    public function testRenderAuthenticationErrorUsesTheMessageKeyNotTheMessage(): void
    {
        $authUtils = $this->createStub(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(
            new AuthenticationException('Internal detail that must not be displayed'),
        );
        $authUtils->method('getLastUsername')->willReturn('');

        $window = new LoginWindow($authUtils);

        $output = $this->render($window);

        $this->assertStringContainsString('id="login-error"', $output);
        $this->assertStringContainsString('[security]An authentication exception occurred.', $output);
        $this->assertStringNotContainsString('Internal detail', $output);
    }
}
