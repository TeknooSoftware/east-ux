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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\ComponentRendererInterface;
use Teknoo\East\Common\Object\User;
use Teknoo\East\UX\Service\Menu\ThemeMenuGenerator;
use Teknoo\East\UX\Service\MenuService;
use Teknoo\East\UX\Service\ThemeService;
use Teknoo\East\UXBundle\Components\Card;
use Teknoo\East\UXBundle\Components\ConfirmModal;
use Teknoo\East\UXBundle\Components\FormWindow;
use Teknoo\East\UXBundle\Components\ListWindow;
use Teknoo\East\UXBundle\Components\LoginWindow;
use Teknoo\East\UXBundle\Components\MenuBar;
use Teknoo\East\UXBundle\Components\ObjectList;
use Teknoo\East\UXBundle\Components\Pagination;
use Teknoo\East\UXBundle\Components\ThemeSwitcher;
use Teknoo\East\UXBundle\Components\Window;
use Teknoo\East\UXBundle\Controller\ComponentRenderer;
use Teknoo\East\UXBundle\Controller\ThemePreferenceUpdater;
use Teknoo\East\UXBundle\DependencyInjection\TeknooEastUXExtension;
use Teknoo\East\UXBundle\TeknooEastUXBundle;
use Teknoo\East\UXBundle\Theme\SessionThemePreferenceStorage;
use Teknoo\East\UXBundle\Twig\ThemeExtension;
use Teknoo\Tests\East\UX\Support\Fixtures\Form\Type\ItemType;
use Teknoo\Tests\East\UX\Support\Fixtures\Kernel;
use Teknoo\Tests\East\UX\Support\Fixtures\Object\Item;

use function array_keys;
use function array_unique;
use function array_values;
use function basename;
use function count;
use function dirname;
use function file_get_contents;
use function glob;
use function json_decode;
use function json_encode;
use function preg_match_all;
use function sort;
use function substr_count;

/**
 * Boots the test application (`Teknoo\Tests\East\UX\Support\Fixtures\Kernel`: East Foundation, East Common, Symfony UX,
 * StimulusBundle, AssetMapper, SecurityBundle and East UX), renders each component through Symfony UX's component
 * renderer, and checks the assets exposed to AssetMapper and StimulusBundle, and the `east_ux` catalogue read by the
 * translator from the bundle's directory.
 *
 * The rendering tests assert the structure of the rendered HTML; the texts of the `east_ux` catalogue are checked by
 * the catalogue tests.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(TeknooEastUXBundle::class)]
#[CoversClass(TeknooEastUXExtension::class)]
#[CoversClass(Card::class)]
#[CoversClass(ConfirmModal::class)]
#[CoversClass(FormWindow::class)]
#[CoversClass(ListWindow::class)]
#[CoversClass(LoginWindow::class)]
#[CoversClass(MenuBar::class)]
#[CoversClass(ObjectList::class)]
#[CoversClass(Pagination::class)]
#[CoversClass(ThemeSwitcher::class)]
#[CoversClass(Window::class)]
#[CoversClass(ComponentRenderer::class)]
#[CoversClass(ThemePreferenceUpdater::class)]
#[CoversClass(MenuService::class)]
#[CoversClass(ThemeMenuGenerator::class)]
#[CoversClass(ThemeService::class)]
#[CoversClass(SessionThemePreferenceStorage::class)]
#[CoversClass(ThemeExtension::class)]
class KernelTest extends TestCase
{
    private ?Kernel $kernel = null;

    /**
     * Cookies sent by the test application, sent back with the next requests as a browser does
     *
     * @var array<string, string>
     */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
        $this->cookies = [];
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    private function getKernel(): Kernel
    {
        self::assertInstanceOf(Kernel::class, $this->kernel);

        return $this->kernel;
    }

    private function getTestContainer(): ContainerInterface
    {
        $container = $this->getKernel()->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function pushRequest(string $uri = 'https://localhost/home', bool $withAuthenticationError = false): void
    {
        $request = Request::create($uri);
        $session = new Session(new MockArraySessionStorage());
        if ($withAuthenticationError) {
            $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, new BadCredentialsException());
        }
        $request->setSession($session);

        $container = $this->getTestContainer();
        $requestStack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);

        $router = $container->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $router->getContext()->fromRequest($request);
    }

    /**
     * A Live Component rendered outside a Twig template needs an `id` attribute in its props: Symfony UX computes the
     * default one from the calling template.
     *
     * @param array<string, mixed> $props
     */
    private function render(string $componentName, array $props = []): Crawler
    {
        $renderer = $this->getTestContainer()->get('ux.twig_component.component_renderer');
        self::assertInstanceOf(ComponentRendererInterface::class, $renderer);

        return new Crawler($renderer->createAndRender($componentName, $props));
    }

    /**
     * @return array<string, mixed>
     */
    private function getLiveProps(Crawler $crawler, string $componentName): array
    {
        $node = $crawler->filter('[data-live-name-value="' . $componentName . '"]');
        self::assertCount(1, $node);

        $props = json_decode((string) $node->attr('data-live-props-value'), true);
        self::assertIsArray($props);

        /** @var array<string, mixed> $props */
        return $props;
    }

    /**
     * @return iterable<string, array{string, class-string, bool}>
     */
    public static function componentsProvider(): iterable
    {
        yield 'Card' => ['Card', Card::class, false];
        yield 'ConfirmModal' => ['ConfirmModal', ConfirmModal::class, false];
        yield 'FormWindow' => ['FormWindow', FormWindow::class, true];
        yield 'ListWindow' => ['ListWindow', ListWindow::class, true];
        yield 'LoginWindow' => ['LoginWindow', LoginWindow::class, false];
        yield 'MenuBar' => ['MenuBar', MenuBar::class, false];
        yield 'ObjectList' => ['ObjectList', ObjectList::class, false];
        yield 'Pagination' => ['Pagination', Pagination::class, false];
        yield 'ThemeSwitcher' => ['ThemeSwitcher', ThemeSwitcher::class, false];
        yield 'Window' => ['Window', Window::class, false];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('componentsProvider')]
    public function testComponentIsRegisteredWithItsShortName(string $name, string $className, bool $isLive): void
    {
        $factory = $this->getTestContainer()->get('ux.twig_component.component_factory');
        self::assertInstanceOf(ComponentFactory::class, $factory);

        $metadata = $factory->metadataFor($name);

        self::assertSame($className, $metadata->getClass());
        self::assertSame('@TeknooEastUX/components/' . $name . '.html.twig', $metadata->getTemplate());
        self::assertSame($isLive, (bool) $metadata->get('live', false));
    }

    public function testRenderWindow(): void
    {
        $this->pushRequest();

        $crawler = $this->render(
            'Window',
            ['title' => 'Kernel window', 'windowId' => 'kernel-window', 'closable' => true, 'draggable' => true],
        );

        $window = $crawler->filter('div.window.draggable');
        self::assertCount(1, $window);
        self::assertSame('kernel-window', $window->attr('data-form-window'));
        self::assertSame('window-close window-drag', $window->attr('data-controller'));
        self::assertSame('Kernel window', $crawler->filter('.title-bar h1.title')->text());
        self::assertSame('click->window-close#close', $crawler->filter('.title-bar button.close')->attr('data-action'));
        self::assertCount(1, $crawler->filter('.window-pane'));
    }

    public function testRenderCardEscapesItsContent(): void
    {
        $this->pushRequest();

        $crawler = $this->render(
            'Card',
            [
                'title' => 'Kernel card',
                'content' => "<script>alert('card')</script>\nSecond line",
                'leftActionPath' => '/home',
                'leftActionText' => 'Back',
            ],
        );

        self::assertCount(0, $crawler->filter('.card-text script'));
        self::assertStringContainsString('&lt;script&gt;', (string) $crawler->filter('.card-text')->html());
        self::assertCount(1, $crawler->filter('.card-text br'));
        self::assertSame('/home', $crawler->filter('a.btn')->attr('href'));
    }

    public function testRenderCardWithHtmlContent(): void
    {
        $this->pushRequest();

        $crawler = $this->render(
            'Card',
            ['title' => 'Kernel card', 'content' => '<em>trusted</em>', 'contentIsHtml' => true],
        );

        self::assertSame('trusted', $crawler->filter('.card-text em')->text());
    }

    public function testRenderConfirmModal(): void
    {
        $this->pushRequest();

        $crawler = $this->render(
            'ConfirmModal',
            ['modalId' => 'kernel-modal', 'title' => 'Sure?', 'confirmText' => 'Yes', 'cancelText' => 'No'],
        );

        self::assertCount(1, $crawler->filter('#kernel-modal.modal .window.confirm-modal'));
        self::assertSame('Yes', $crawler->filter('[data-confirm-button]')->text());
    }

    public function testRenderObjectListWithoutObjects(): void
    {
        $this->pushRequest();

        $crawler = $this->render('ObjectList', ['objectsCollection' => []]);

        self::assertCount(0, $crawler->filter('table'));
        self::assertCount(1, $crawler->filter('div.alert.alert-info'));
        // Text of the `east_ux` catalogue of the bundle
        self::assertSame('No results found', $crawler->filter('div.alert.alert-info')->text());
    }

    public function testRenderObjectListWithAnItemComponent(): void
    {
        $this->pushRequest();

        $crawler = $this->render(
            'ObjectList',
            [
                'objectsCollection' => [new Item('item-1', 'First'), new Item('item-2', 'Second')],
                'itemComponent' => 'ItemRow',
                'tableHeaders' => ['fixtures.items.name', 'fixtures.items.actions'],
            ],
        );

        // Headers are translation keys of the application, in its default domain
        self::assertSame(
            ['Name', 'Actions'],
            $crawler->filter('thead th')->each(static fn (Crawler $th) => $th->text()),
        );
        self::assertSame(
            ['First', 'Second'],
            $crawler->filter('tbody tr.item-row .item-name')->each(static fn (Crawler $td) => $td->text()),
        );
    }

    public function testRenderPagination(): void
    {
        $this->pushRequest();

        $crawler = $this->render('Pagination', ['pageCount' => 3, 'page' => 2]);

        self::assertSame('2', $crawler->filter('li.page-item.active button')->attr('data-value'));
        self::assertCount(5, $crawler->filter('li.page-item'));
        self::assertCount(5, $crawler->filter('button[data-model="page"][data-action="live#update"]'));
    }

    public function testRenderMenuBarWithoutUser(): void
    {
        $this->pushRequest();

        $crawler = $this->render('MenuBar');

        // The brand links to the route `home_route` of the bundle configuration
        self::assertSame('/home', $crawler->filter('a.navbar-brand')->attr('href'));
        self::assertCount(0, $crawler->filter('li.nav-item'));
    }

    public function testRenderMenuBarWithAUser(): void
    {
        $this->pushRequest();

        $user = (new User())
            ->setEmail('jane@teknoo.test')
            ->setFirstName('Jane')
            ->setLastName('<Tester>')
            ->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $crawler = $this->render('MenuBar', ['user' => $user]);

        self::assertSame(
            ['Home section', 'Content', 'Administration'],
            $crawler->filter('ul.me-auto > li > a.dropdown-toggle')->each(static fn (Crawler $a) => $a->text()),
        );
        // The theme switcher of `ThemeMenuGenerator` (tagged with a lower priority) follows the user dropdown
        self::assertSame(
            ['Jane <Tester>', 'Theme'],
            $crawler->filter('ul.ms-auto > li > a.dropdown-toggle')->each(static fn (Crawler $a) => $a->text()),
        );
        self::assertStringContainsString('Jane &lt;Tester&gt;', (string) $crawler->filter('ul.ms-auto')->html());
        self::assertCount(1, $crawler->filter('ul.ms-auto > li')->first()->filter('hr.dropdown-divider'));
        self::assertSame('/home?page=settings', $crawler->filter('ul.ms-auto a.dropdown-item')->first()->attr('href'));

        // Labels of the `east_ux` catalogue, buttons of the `theme` controller
        $theme = $crawler->filter('ul.ms-auto > li')->last();
        self::assertSame(
            ['Day', 'Night', 'Automatic', 'Classic', 'Modern'],
            $theme->filter('button.dropdown-item')->each(static fn (Crawler $button) => $button->text()),
        );
        self::assertCount(1, $theme->filter('hr.dropdown-divider'));
        self::assertCount(
            5,
            $theme->filter(
                'button[data-action="click->theme#select"][data-theme-target="menuOption"][role="menuitemradio"]'
            ),
        );
        self::assertSame(
            'dark',
            $theme->filter('button.dropdown-item')->eq(1)->attr('data-theme-mode-param'),
        );
        self::assertSame(
            'modern',
            $theme->filter('button.dropdown-item')->eq(4)->attr('data-theme-style-param'),
        );
    }

    public function testRenderThemeSwitcher(): void
    {
        $this->pushRequest();

        $crawler = $this->render('ThemeSwitcher', ['showStyle' => false]);

        self::assertSame('Theme', $crawler->filter('div.east-ux-theme-switcher')->attr('aria-label'));
        self::assertSame(
            ['Day', 'Night', 'Automatic'],
            $crawler->filter('button[data-theme-mode-param]')->each(static fn (Crawler $button) => $button->text()),
        );
        self::assertCount(0, $crawler->filter('button[data-theme-style-param]'));
        // Default mode of the configuration
        self::assertSame('auto', $crawler->filter('button[aria-pressed="true"]')->attr('data-theme-mode-param'));
    }

    public function testRenderLoginWindow(): void
    {
        $this->pushRequest('https://localhost/login', true);

        $crawler = $this->render('LoginWindow', ['title' => 'Sign in', 'actionPath' => '/login']);

        self::assertSame('/login', $crawler->filter('form')->attr('action'));
        self::assertCount(1, $crawler->filter('input[name="_username"]'));
        self::assertCount(1, $crawler->filter('input[name="_password"]'));
        self::assertCount(1, $crawler->filter('input[name="_remember_me"]'));
        self::assertNotEmpty($crawler->filter('input[name="_csrf_token"]')->attr('value'));
        // Error translated from its message key in the `security` domain
        self::assertStringContainsString('Invalid credentials.', $crawler->filter('#login-error')->text());
    }

    public function testRenderLoginWindowWithoutRememberMeAndCsrf(): void
    {
        $this->pushRequest('https://localhost/login');

        $crawler = $this->render(
            'LoginWindow',
            ['title' => 'Sign in', 'actionPath' => '/login', 'rememberMe' => false, 'csrfTokenId' => ''],
        );

        self::assertCount(0, $crawler->filter('#login-error'));
        self::assertCount(0, $crawler->filter('input[name="_remember_me"]'));
        self::assertCount(0, $crawler->filter('input[name="_csrf_token"]'));
    }

    public function testRenderFormWindowWithTheFormThemeOfTheConfiguration(): void
    {
        $this->pushRequest('https://localhost/items/new');

        $crawler = $this->render(
            'FormWindow',
            [
                'title' => 'New item',
                'windowId' => 'kernel-form',
                'formName' => ItemType::class,
                'objectInstance' => new Item(null, 'Kernel item'),
                'formPath' => '/items/new',
                'hidden' => false,
                'id' => 'kernel-form-window',
            ],
        );

        $window = $crawler->filter('div.window.form-window');
        self::assertCount(1, $window);
        self::assertSame('kernel-form-window', $window->attr('id'));
        self::assertSame('kernel-form', $window->attr('data-form-window'));
        self::assertStringContainsString('form-window', (string) $window->attr('data-controller'));
        self::assertStringContainsString('live', (string) $window->attr('data-controller'));
        // The messages of the `form-window` controller come from the `east_ux` catalogue
        self::assertSame('An error occurred while saving.', $window->attr('data-form-window-error-text-value'));
        self::assertSame('A network error occurred.', $window->attr('data-form-window-network-error-text-value'));

        self::assertSame('/items/new', $crawler->filter('form')->attr('action'));
        $input = $crawler->filter('input[name="item[name]"]');
        self::assertSame('Kernel item', $input->attr('value'));
        // `teknoo_east_ux.form_theme` of the test application is Bootstrap 5, the application has no global form theme
        self::assertSame('form-control', $input->attr('class'));

        $props = $this->getLiveProps($crawler, 'FormWindow');
        self::assertSame('bootstrap_5_layout.html.twig', $props['formTheme']);
        self::assertSame('/items/new', $props['originalPath']);
        self::assertArrayHasKey('@checksum', $props);
    }

    public function testRenderFormWindowWithoutFormTheme(): void
    {
        $this->pushRequest('https://localhost/items/new');

        $crawler = $this->render(
            'FormWindow',
            [
                'formName' => ItemType::class,
                'objectInstance' => new Item(),
                'formPath' => '/items/new',
                'formTheme' => null,
                'id' => 'kernel-form-window',
            ],
        );

        self::assertNull($crawler->filter('input[name="item[name]"]')->attr('class'));
    }

    public function testRenderListWindow(): void
    {
        $this->pushRequest('https://localhost/items/');

        $crawler = $this->render(
            'ListWindow',
            [
                'title' => 'Items',
                'objectsCollection' => [new Item('item-1', 'First'), new Item('item-2', 'Second')],
                'pageCount' => 2,
                'page' => 1,
                'itemComponent' => 'ItemRow',
                'newFormRoute' => 'fixtures_item_new',
                'newFormName' => ItemType::class,
                'id' => 'kernel-list-window',
            ],
        );

        $window = $crawler->filter('div.window[data-live-name-value="ListWindow"]');
        self::assertCount(1, $window);
        self::assertSame('list-window-refresh live', $window->attr('data-controller'));
        self::assertCount(2, $crawler->filter('tr.item-row'));
        self::assertCount(1, $crawler->filter('ul.pagination'));

        $newButton = $crawler->filter('button[data-controller="form-window-dynamic-loader"].ms-auto');
        self::assertSame('/component/render', $newButton->attr('data-form-window-dynamic-loader-url-value'));
        self::assertSame('/items/new', $newButton->attr('data-form-window-dynamic-loader-form-path-value'));
        self::assertSame(ItemType::class, $newButton->attr('data-form-window-dynamic-loader-form-name-value'));
        self::assertSame(
            'An error occurred while loading the form.',
            $newButton->attr('data-form-window-dynamic-loader-load-error-text-value'),
        );

        self::assertCount(1, $crawler->filter('#delete-confirm-modal [data-confirm-button]'));
        // Without search form name, no search form is rendered
        self::assertCount(0, $crawler->filter('form'));

        $props = $this->getLiveProps($crawler, 'ListWindow');
        self::assertSame('/items/', $props['originalPath']);
        self::assertArrayNotHasKey('objectsCollection', $props);
    }

    public function testDebugAssetMapListsTheAssetsOfTheLibrary(): void
    {
        $application = new Application($this->getKernel());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $output = new BufferedOutput();
        // Without `--full`, long logical paths are shortened in the table
        $exitCode = $application->run(new ArrayInput(['command' => 'debug:asset-map', '--full' => true]), $output);
        $content = $output->fetch();

        self::assertSame(0, $exitCode, $content);
        self::assertStringContainsString('@teknoo/east-ux/controllers/form_window_controller.js', $content);
        self::assertStringContainsString('@teknoo/east-ux/styles/east-ux.css', $content);
        self::assertStringContainsString('@teknoo/east-ux/controllers/theme_controller.js', $content);
        self::assertStringContainsString('@teknoo/east-ux/system-theme/css/bootstrap-system-theme.min.css', $content);
        self::assertStringContainsString('@teknoo/east-ux/system-theme/fonts/ChicagoFLF.woff2', $content);
        self::assertStringContainsString('@teknoo/east-ux/system-theme/fonts/monaco.woff2', $content);
        self::assertStringContainsString('@teknoo/east-ux/system-theme/fonts/ChiKareGo2.woff2', $content);
    }

    public function testTheStylesheetOfTheThemeReferencesItsFonts(): void
    {
        $assetMapper = $this->getTestContainer()->get('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $assetMapper);

        $stylesheet = $assetMapper->getAsset('@teknoo/east-ux/system-theme/css/bootstrap-system-theme.min.css');
        self::assertNotNull($stylesheet);

        // The relative `url(../fonts/...)` are compiled by AssetMapper to the versioned files of the fonts
        foreach (['ChicagoFLF.woff', 'ChicagoFLF.woff2', 'monaco.woff2', 'ChiKareGo2.woff2'] as $font) {
            $asset = $assetMapper->getAsset('@teknoo/east-ux/system-theme/fonts/' . $font);
            self::assertNotNull($asset, $font);
            self::assertStringContainsString(
                'url("../fonts/' . basename($asset->publicPath) . '")',
                (string) $stylesheet->content,
            );
        }
    }

    public function testStimulusRegistersTheControllersUnderTheirShortIdentifiers(): void
    {
        $assetMapper = $this->getTestContainer()->get('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $assetMapper);

        $controllers = $assetMapper->getAsset('@symfony/stimulus-bundle/controllers.js');
        self::assertNotNull($controllers);

        foreach (
            [
                'form-window' => 'form_window_controller.js',
                'form-window-dynamic-loader' => 'form_window_dynamic_loader_controller.js',
                'list-window-refresh' => 'list_window_refresh_controller.js',
                'window-close' => 'window_close_controller.js',
                'window-drag' => 'window_drag_controller.js',
                'delete-item' => 'delete_item_controller.js',
                'clipboard' => 'clipboard_controller.js',
                'theme' => 'theme_controller.js',
            ] as $identifier => $file
        ) {
            self::assertStringContainsString('@teknoo/east-ux/controllers/' . $file, (string) $controllers->content);
            self::assertStringContainsString('"' . $identifier . '": controller_', (string) $controllers->content);
        }
    }

    /**
     * @return list<string>
     */
    private static function getTranslationKeysOfTheTemplates(): array
    {
        $templatesDirectory = dirname(__DIR__, 3) . '/infrastructures/symfony/templates';
        $templates = [
            ...(glob($templatesDirectory . '/*.html.twig') ?: []),
            ...(glob($templatesDirectory . '/components/*.html.twig') ?: []),
        ];
        self::assertNotEmpty($templates);

        $keys = [];
        foreach ($templates as $template) {
            $content = (string) file_get_contents($template);
            preg_match_all("#'(teknoo\.east\.ux\.[a-z_.]+)'\|trans\(\{\}, 'east_ux'\)#", $content, $matches);

            // Every key of the library is translated in the `east_ux` domain, given explicitly
            self::assertSame(substr_count($content, "'teknoo.east.ux."), count($matches[1]), $template);

            $keys = [...$keys, ...$matches[1]];
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    public function testCatalogueTranslatesEveryKeyOfTheTemplates(): void
    {
        $translator = $this->getTestContainer()->get('translator');
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);

        $catalogue = $translator->getCatalogue('en');
        $keys = self::getTranslationKeysOfTheTemplates();
        self::assertNotEmpty($keys);

        foreach ($keys as $key) {
            self::assertTrue($catalogue->defines($key, 'east_ux'), $key . ' is missing in east_ux.en.yaml');
            self::assertNotSame($key, $catalogue->get($key, 'east_ux'));
        }

        // The catalogue holds no key unused by the templates
        $catalogueKeys = array_keys($catalogue->all('east_ux'));
        sort($catalogueKeys);
        self::assertSame($keys, $catalogueKeys);
    }

    public function testComponentRendererIsReachedThroughEastFoundationRouter(): void
    {
        $response = $this->getKernel()->handle(
            Request::create(
                uri: 'https://localhost/component/render',
                method: 'POST',
                server: [
                    'PHP_AUTH_USER' => 'user',
                    'PHP_AUTH_PW' => 'user-password',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                    'CONTENT_TYPE' => 'application/json',
                ],
                content: (string) json_encode(['component' => 'FormWindow']),
            ),
        );

        // The 400 of the controller, not a response of East Foundation's router
        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * @param array<string, string> $server
     */
    private function handle(string $method, string $path, array $server = [], ?string $content = null): Response
    {
        $request = Request::create(
            uri: 'https://localhost' . $path,
            method: $method,
            cookies: $this->cookies,
            server: $server,
            content: $content,
        );

        $kernel = $this->getKernel();
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $response;
    }

    private function sendTheme(string $content): Response
    {
        return $this->handle(
            method: 'POST',
            path: '/east-ux/theme',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            content: $content,
        );
    }

    public function testPagesAreRenderedWithTheDefaultTheme(): void
    {
        $response = $this->handle('GET', '/home');
        self::assertSame(200, $response->getStatusCode());

        $crawler = new Crawler((string) $response->getContent());
        $html = $crawler->filter('html');
        self::assertSame('', $html->attr('class'));
        // Legacy style and auto mode: `data-bs-theme` is set by the script, from `prefers-color-scheme`
        self::assertNull($html->attr('data-bs-theme'));
        self::assertSame('theme', $html->attr('data-controller'));
        self::assertSame('legacy', $html->attr('data-theme-style-value'));
        self::assertSame('auto', $html->attr('data-theme-mode-value'));
        self::assertSame('session', $html->attr('data-theme-storage-value'));
        self::assertSame('/east-ux/theme', $html->attr('data-theme-url-value'));
        self::assertStringContainsString('prefers-color-scheme', $crawler->filter('head script')->text());

        self::assertSame(
            ['auto', 'legacy'],
            $crawler->filter('#theme-switcher button[aria-pressed="true"]')->each(
                static fn (Crawler $button): string => (string) (
                    $button->attr('data-theme-mode-param') ?? $button->attr('data-theme-style-param')
                ),
            ),
        );
    }

    public function testTheThemeChosenByTheVisitorIsKeptInTheSession(): void
    {
        $response = $this->sendTheme((string) json_encode(['style' => 'modern', 'mode' => 'dark']));
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        // The session cookie
        self::assertNotEmpty($this->cookies);

        $page = $this->handle('GET', '/home');
        self::assertSame(200, $page->getStatusCode());

        $crawler = new Crawler((string) $page->getContent());
        $html = $crawler->filter('html');
        self::assertSame('system-modern', $html->attr('class'));
        self::assertSame('dark', $html->attr('data-bs-theme'));
        self::assertSame('modern', $html->attr('data-theme-style-value'));
        self::assertSame('dark', $html->attr('data-theme-mode-value'));

        // Only the mode changes, the style is kept
        self::assertSame(204, $this->sendTheme((string) json_encode(['mode' => 'light']))->getStatusCode());

        $html = new Crawler((string) $this->handle('GET', '/home')->getContent())->filter('html');
        self::assertSame('system-modern', $html->attr('class'));
        self::assertSame('light', $html->attr('data-bs-theme'));
        self::assertSame('light', $html->attr('data-theme-mode-value'));
    }

    public function testAnotherVisitorKeepsTheDefaultTheme(): void
    {
        self::assertSame(204, $this->sendTheme((string) json_encode(['style' => 'modern']))->getStatusCode());

        // Without the session cookie of the first visitor
        $this->cookies = [];
        $html = new Crawler((string) $this->handle('GET', '/home')->getContent())->filter('html');

        self::assertSame('', $html->attr('class'));
        self::assertSame('legacy', $html->attr('data-theme-style-value'));
    }

    public function testTheThemeEndpointRefusesInvalidChoices(): void
    {
        // Reached through East Foundation's router: the responses of the controller
        self::assertSame(400, $this->sendTheme((string) json_encode(['style' => 'retro']))->getStatusCode());

        $response = $this->handle(
            method: 'POST',
            path: '/east-ux/theme',
            server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            content: 'style=modern',
        );
        self::assertSame(415, $response->getStatusCode());

        self::assertSame(405, $this->handle('GET', '/east-ux/theme')->getStatusCode());
    }
}
