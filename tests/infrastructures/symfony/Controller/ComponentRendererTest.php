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

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Teknoo\East\CommonBundle\Contracts\Rendering\ComponentFactoryInterface;
use Teknoo\East\UXBundle\ComponentRendering\Exception\InvalidPropsException;
use Teknoo\East\UXBundle\ComponentRendering\Exception\UnreferencedComponentException;
use Teknoo\East\UXBundle\ComponentRendering\PropsValidatorRegistry;
use Teknoo\East\UXBundle\Components\Card;
use Teknoo\East\UXBundle\Components\FormWindow;
use Teknoo\East\UXBundle\Contracts\ComponentRendering\PropsValidatorInterface;
use Teknoo\East\UXBundle\Controller\ComponentRenderer;
use Teknoo\Recipe\Promise\PromiseInterface;
use Twig\Environment;

use function json_encode;

/**
 * Controller handling component renderer HTTP requests.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(ComponentRenderer::class)]
class ComponentRendererTest extends TestCase
{
    /**
     * Factory of a component unknown by Symfony UX (`TestComponent`), whose rendering is allowed by default
     */
    private function buildController(
        ?PropsValidatorRegistry $registry = null,
        ?ComponentFactoryInterface $componentFactory = null,
    ): ComponentRenderer {
        if (null === $componentFactory) {
            $componentFactory = $this->createStub(ComponentFactoryInterface::class);
            $componentFactory->method('metadataFor')->willThrowException(new InvalidArgumentException());
        }

        return new class (
            $registry ?? new PropsValidatorRegistry([], true),
            $componentFactory,
        ) extends ComponentRenderer {
            private ?Response $mockResponse = null;

            public function setMockResponse(Response $response): void
            {
                $this->mockResponse = $response;
            }

            protected function render(string $view, array $parameters = [], ?Response $response = null): Response
            {
                if ($this->mockResponse !== null) {
                    return $this->mockResponse;
                }

                return new Response('rendered');
            }
        };
    }

    public function testConstruct(): void
    {
        $controller = $this->buildController();
        $this->assertInstanceOf(ComponentRenderer::class, $controller);
    }

    public function testInvokeWithValidData(): void
    {
        $controller = $this->buildController();
        $mockResponse = new Response('test response');
        $controller->setMockResponse($mockResponse);

        $requestData = json_encode([
            'component' => 'TestComponent',
            'props' => ['foo' => 'bar'],
        ]);

        $request = new Request([], [], [], [], [], [], $requestData);

        $response = $controller($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('test response', $response->getContent());
    }

    public function testInvokeMissingComponent(): void
    {
        $controller = $this->buildController();

        $requestData = json_encode([
            'props' => ['foo' => 'bar'],
        ]);

        $request = new Request([], [], [], [], [], [], $requestData);

        $response = $controller($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Missing component or props', $response->getContent());
    }

    public function testInvokeMissingProps(): void
    {
        $controller = $this->buildController();

        $requestData = json_encode([
            'component' => 'TestComponent',
        ]);

        $request = new Request([], [], [], [], [], [], $requestData);

        $response = $controller($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Missing component or props', $response->getContent());
    }

    public function testInvokeInvalidJson(): void
    {
        $controller = $this->buildController();

        $request = new Request([], [], [], [], [], [], 'invalid json');

        $response = $controller($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Missing component or props', $response->getContent());
    }

    private function buildComponentFactory(string $name, ?string $class): ComponentFactoryInterface
    {
        $config = ['key' => $name, 'template' => 'components/' . $name . '.html.twig'];
        if (null !== $class) {
            $config['class'] = $class;
            $config['service_id'] = $class;
        }

        $componentFactory = $this->createMock(ComponentFactoryInterface::class);
        $componentFactory->expects($this->once())
            ->method('metadataFor')
            ->with($name)
            ->willReturn(new ComponentMetadata($config));

        return $componentFactory;
    }

    /**
     * Registry with a validator of FormWindow, valid or not, and the option allowing the unreferenced components
     */
    private function buildRegistry(bool $validProps, bool $allowUnreferencedComponents = false): PropsValidatorRegistry
    {
        $validator = $this->createStub(PropsValidatorInterface::class);
        $validator->method('validate')->willReturnCallback(
            static function (array $props, PromiseInterface $promise) use ($validator, $validProps): PropsValidatorInterface {
                if ($validProps) {
                    $promise->success($props);
                } else {
                    $promise->fail(new InvalidPropsException('invalid'));
                }

                return $validator;
            }
        );

        return new PropsValidatorRegistry([FormWindow::class => $validator], $allowUnreferencedComponents);
    }

    /**
     * @param array<string, mixed>|string $props
     */
    private function buildRequest(mixed $component, array|string $props): Request
    {
        return new Request(
            [],
            [],
            [],
            [],
            [],
            [],
            (string) json_encode(['component' => $component, 'props' => $props]),
        );
    }

    public function testInvokeWithAReferencedComponentAndValidProps(): void
    {
        $controller = $this->buildController(
            $this->buildRegistry(true),
            $this->buildComponentFactory('FormWindow', FormWindow::class),
        );

        $response = $controller($this->buildRequest('FormWindow', ['windowId' => 'foo']));

        $this->assertEquals('rendered', $response->getContent());
    }

    public function testInvokeWithAReferencedComponentAndInvalidProps(): void
    {
        $controller = $this->buildController(
            $this->buildRegistry(false, true),
            $this->buildComponentFactory('FormWindow', FormWindow::class),
        );

        $this->expectException(InvalidPropsException::class);
        $controller($this->buildRequest('FormWindow', ['windowId' => 1]));
    }

    public function testInvokeWithPropsNotInAnObject(): void
    {
        $componentFactory = $this->createMock(ComponentFactoryInterface::class);
        $componentFactory->expects($this->never())->method('metadataFor');

        $controller = $this->buildController($this->buildRegistry(true, true), $componentFactory);

        $this->expectException(InvalidPropsException::class);
        $controller($this->buildRequest('FormWindow', 'foo'));
    }

    public function testInvokeWithAnUnreferencedComponentRefused(): void
    {
        $controller = $this->buildController(
            $this->buildRegistry(true),
            $this->buildComponentFactory('Card', Card::class),
        );

        $this->expectException(UnreferencedComponentException::class);
        $controller($this->buildRequest('Card', ['title' => 'foo']));
    }

    public function testInvokeWithAnUnreferencedComponentAllowed(): void
    {
        $controller = $this->buildController(
            $this->buildRegistry(true, true),
            $this->buildComponentFactory('Card', Card::class),
        );

        $response = $controller($this->buildRequest('Card', ['title' => 'foo']));

        $this->assertEquals('rendered', $response->getContent());
    }

    public function testInvokeWithAnAnonymousComponentRefused(): void
    {
        $controller = $this->buildController(
            $this->buildRegistry(true),
            $this->buildComponentFactory('Row', null),
        );

        $this->expectException(UnreferencedComponentException::class);
        $controller($this->buildRequest('Row', ['object' => 'foo']));
    }

    public function testInvokeWithAnUnknownComponentRefused(): void
    {
        $controller = $this->buildController($this->buildRegistry(true));

        $this->expectException(UnreferencedComponentException::class);
        $controller($this->buildRequest('TestComponent', ['foo' => 'bar']));
    }

    public function testInvokeWithAComponentNameNotAStringRefused(): void
    {
        $componentFactory = $this->createMock(ComponentFactoryInterface::class);
        $componentFactory->expects($this->once())
            ->method('metadataFor')
            ->with('')
            ->willThrowException(new InvalidArgumentException());

        $controller = $this->buildController($this->buildRegistry(true), $componentFactory);

        $this->expectException(UnreferencedComponentException::class);
        $controller($this->buildRequest(42, []));
    }
}
