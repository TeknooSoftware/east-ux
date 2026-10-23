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

namespace Teknoo\Tests\East\UX\Behat;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Teknoo\Tests\East\UX\Support\Fixtures\Form\Type\ItemType;
use Teknoo\Tests\East\UX\Support\Fixtures\Kernel;
use Teknoo\Tests\East\UX\Support\Fixtures\Object\Item;
use Teknoo\Tests\East\UX\Support\Fixtures\Repository\ItemRepository;

use function array_map;
use function explode;
use function is_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function parse_url;
use function str_replace;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_URL_PATH;

/**
 * Behat context of East UX: drives the test application (`Teknoo\Tests\East\UX\Support\Fixtures\Kernel`, also booted
 * by the kernel test) with HTTP requests, as a browser and the Stimulus controllers of East UX do: pages rendered by
 * East Common's recipes, Live Component re-renders (`POST /_components/<Name>` with the props of the rendered
 * component, re-routed by East Foundation to the recipe of `originalPath`) and requests to the component renderer
 * (`POST /component/render`), and the theme chosen by the visitor (`POST /east-ux/theme`, as the `theme` controller).
 *
 * A scenario uses one kernel, so the items it creates are kept between its requests, and the cookies of the
 * responses are sent back with the next requests, as a browser does (the session holds the theme of the visitor).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class FeatureContext implements Context
{
    private const string BASE_URL = 'https://localhost';

    /**
     * Passwords of the in-memory users of the test application
     */
    private const array PASSWORDS = [
        'user' => 'user-password',
        'admin' => 'admin-password',
    ];

    /**
     * Short names usable in the features for the form types
     */
    private const array FORM_TYPES = [
        'ItemType' => ItemType::class,
        'TextType' => TextType::class,
    ];

    private ?Kernel $kernel = null;

    /**
     * @var array<string, string>
     */
    private array $credentials = [];

    private ?Response $response = null;

    /**
     * @var array<string, string>
     */
    private array $cookies = [];

    /**
     * Props of the last rendered component, per component name, as `live_controller.js` reads them from the HTML
     *
     * @var array<string, array<string, mixed>>
     */
    private array $liveProps = [];

    #[BeforeScenario]
    public function clean(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        $this->credentials = [];
        $this->response = null;
        $this->cookies = [];
        $this->liveProps = [];
    }

    #[AfterScenario]
    public function shutdownKernel(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    private function getKernel(): Kernel
    {
        Assert::assertInstanceOf(Kernel::class, $this->kernel, 'The application is not started');

        return $this->kernel;
    }

    private function getResponse(): Response
    {
        Assert::assertInstanceOf(Response::class, $this->response, 'No request was sent');

        return $this->response;
    }

    private function getCrawler(): Crawler
    {
        return new Crawler((string) $this->getResponse()->getContent());
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string> $server
     */
    private function sendRequest(
        string $method,
        string $path,
        array $parameters = [],
        array $server = [],
        ?string $content = null,
    ): void {
        $request = Request::create(
            uri: self::BASE_URL . $path,
            method: $method,
            parameters: $parameters,
            cookies: $this->cookies,
            server: $this->credentials + $server,
            content: $content,
        );

        $kernel = $this->getKernel();
        $this->response = $kernel->handle($request);
        $kernel->terminate($request, $this->response);

        foreach ($this->response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        $this->rememberLiveProps();
    }

    private function rememberLiveProps(): void
    {
        $this->getCrawler()->filter('[data-live-name-value]')->each(
            function (Crawler $node): void {
                $props = json_decode((string) $node->attr('data-live-props-value'), true);
                if (is_array($props)) {
                    /** @var array<string, mixed> $props */
                    $this->liveProps[(string) $node->attr('data-live-name-value')] = $props;
                }
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getLiveProps(string $componentName): array
    {
        Assert::assertArrayHasKey(
            $componentName,
            $this->liveProps,
            "No live component $componentName was rendered",
        );

        return $this->liveProps[$componentName];
    }

    /**
     * Sends the request of `live_controller.js` for a re-render: a `POST` to the Live Component endpoint with the
     * props of the rendered component (checksum included) and the updated props, in the form field `data`.
     *
     * @param array<string, mixed> $updated
     */
    private function reRender(string $componentName, array $updated = []): void
    {
        $this->sendRequest(
            method: 'POST',
            path: '/_components/' . $componentName,
            parameters: [
                'data' => json_encode(
                    ['props' => $this->getLiveProps($componentName), 'updated' => (object) $updated],
                    JSON_THROW_ON_ERROR,
                ),
            ],
            server: [
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'HTTP_ACCEPT' => 'application/vnd.live-component+html',
            ],
        );
    }

    /**
     * Sends the request of the `form-window-dynamic-loader` Stimulus controller to the component renderer.
     */
    private function callComponentRenderer(string $body): void
    {
        $this->sendRequest(
            method: 'POST',
            path: '/component/render',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            content: $body,
        );
    }

    private function convertPropValue(string $value): bool|int|string
    {
        return match (true) {
            'true' === $value => true,
            'false' === $value => false,
            is_numeric($value) => (int) $value,
            isset(self::FORM_TYPES[$value]) => self::FORM_TYPES[$value],
            default => $value,
        };
    }

    /**
     * @return list<string>
     */
    private function splitList(string $list): array
    {
        if ('' === trim($list)) {
            return [];
        }

        return array_map(trim(...), explode(',', $list));
    }

    private function findFormWindow(string $windowId): Crawler
    {
        $window = $this->getCrawler()->filter('div.window.form-window[data-form-window="' . $windowId . '"]');
        Assert::assertCount(1, $window, "The form window $windowId is not rendered");

        return $window;
    }

    #[Given('a Symfony application with East UX')]
    public function aSymfonyApplicationWithEastUx(): void
    {
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
    }

    #[Given(':count items in the application')]
    public function itemsInTheApplication(int $count): void
    {
        $repository = $this->getKernel()->getContainer()->get(ItemRepository::class);
        Assert::assertInstanceOf(ItemRepository::class, $repository);

        for ($index = 1; $index <= $count; ++$index) {
            $repository->add(new Item(name: "Item $index"));
        }
    }

    #[Given('I am authenticated as :username')]
    public function iAmAuthenticatedAs(string $username): void
    {
        Assert::assertArrayHasKey($username, self::PASSWORDS);

        $this->credentials = [
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => self::PASSWORDS[$username],
        ];
    }

    #[Given('I am not authenticated')]
    public function iAmNotAuthenticated(): void
    {
        $this->credentials = [];
    }

    #[When('I open the page :path')]
    public function iOpenThePage(string $path): void
    {
        $this->sendRequest('GET', $path);
    }

    #[When('I submit the form of the page :path with the name :name')]
    public function iSubmitTheFormOfThePageWithTheName(string $path, string $name): void
    {
        $this->sendRequest('POST', $path, ['item' => ['name' => $name]]);
    }

    #[When('the list window is re-rendered on the page :page')]
    public function theListWindowIsReRenderedOnThePage(int $page): void
    {
        $this->reRender('ListWindow', ['page' => $page]);
    }

    #[When('the form window is opened')]
    public function theFormWindowIsOpened(): void
    {
        // `form-window#open` re-renders the window (`component.render()`) before showing it
        $this->reRender('FormWindow');
    }

    #[When('the component renderer is asked for a :component with the props:')]
    public function theComponentRendererIsAskedForAWithTheProps(string $component, TableNode $props): void
    {
        $values = [];
        foreach ($props->getRowsHash() as $name => $value) {
            Assert::assertIsString($value, "The prop $name must have one value");
            $values[(string) $name] = $this->convertPropValue($value);
        }

        $this->callComponentRenderer(
            (string) json_encode(['component' => $component, 'props' => $values], JSON_THROW_ON_ERROR),
        );
    }

    #[When('the component renderer is asked for a :component with the form :form on the path :path')]
    public function theComponentRendererIsAskedForAWithTheFormOnThePath(
        string $component,
        string $form,
        string $path,
    ): void {
        $this->callComponentRenderer(
            (string) json_encode(
                [
                    'component' => $component,
                    'props' => [
                        'windowId' => 'dynamic-window',
                        'formPath' => $path,
                        'formName' => self::FORM_TYPES[$form] ?? $form,
                        'title' => 'Dynamic window',
                        'hidden' => true,
                    ],
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    #[When('the component renderer receives the body :body')]
    public function theComponentRendererReceivesTheBody(string $body): void
    {
        $this->callComponentRenderer($body);
    }

    #[Then('the response status must be :status')]
    public function theResponseStatusMustBe(int $status): void
    {
        Assert::assertSame($status, $this->getResponse()->getStatusCode());
    }

    #[Then('the client must be redirected to :path')]
    public function theClientMustBeRedirectedTo(string $path): void
    {
        $response = $this->getResponse();
        Assert::assertSame(302, $response->getStatusCode());
        Assert::assertSame($path, parse_url((string) $response->headers->get('location'), PHP_URL_PATH));
    }

    #[Then('the list window must show the items :names')]
    public function theListWindowMustShowTheItems(string $names): void
    {
        $listWindow = $this->getCrawler()->filter('div.window[data-live-name-value="ListWindow"]');
        Assert::assertCount(1, $listWindow);

        Assert::assertSame(
            $this->splitList($names),
            $listWindow->filter('tr.item-row .item-name')->each(static fn (Crawler $cell): string => $cell->text()),
        );
    }

    #[Then('the list window must be on the page :page of :pageCount')]
    public function theListWindowMustBeOnThePageOf(int $page, int $pageCount): void
    {
        $crawler = $this->getCrawler();

        Assert::assertSame((string) $page, $crawler->filter('li.page-item.active button')->attr('data-value'));
        // Buttons of the page numbers, without the previous and next buttons
        Assert::assertCount(
            $pageCount,
            $crawler->filter('li.page-item button[data-model="page"]')->reduce(
                static fn (Crawler $button): bool => $button->text() === $button->attr('data-value'),
            ),
        );
        Assert::assertSame($page, $this->getLiveProps('ListWindow')['page'] ?? null);
    }

    #[Then('the :component must be re-rendered by the route :path')]
    public function theMustBeReRenderedByTheRoute(string $component, string $path): void
    {
        $props = $this->getLiveProps($component);

        Assert::assertSame($path, $props['originalPath'] ?? null);
        Assert::assertArrayHasKey('@checksum', $props);
    }

    #[Then('the list window must provide the delete confirmation modal')]
    public function theListWindowMustProvideTheDeleteConfirmationModal(): void
    {
        Assert::assertCount(1, $this->getCrawler()->filter('#delete-confirm-modal [data-confirm-button]'));
    }

    #[Then('the list window must provide a button opening the form window of :path')]
    public function theListWindowMustProvideAButtonOpeningTheFormWindowOf(string $path): void
    {
        $button = $this->getCrawler()->filter(
            'button[data-controller="form-window-dynamic-loader"][data-form-window-dynamic-loader-form-path-value="'
            . $path . '"]',
        );

        Assert::assertCount(1, $button);
        Assert::assertSame('/component/render', $button->attr('data-form-window-dynamic-loader-url-value'));
        Assert::assertSame('click->form-window-dynamic-loader#open', $button->attr('data-action'));
    }

    #[Then('the form window :windowId must be hidden and without form')]
    public function theFormWindowMustBeHiddenAndWithoutForm(string $windowId): void
    {
        $window = $this->findFormWindow($windowId);

        Assert::assertStringContainsString('d-none', (string) $window->attr('class'));
        Assert::assertCount(0, $window->filter('form'));
    }

    #[Then('the form window :windowId must be live')]
    public function theFormWindowMustBeLive(string $windowId): void
    {
        $window = $this->findFormWindow($windowId);

        Assert::assertSame('form-window live', $window->attr('data-controller'));
        Assert::assertSame('/_components/FormWindow', $window->attr('data-live-url-value'));
        Assert::assertTrue($this->getLiveProps('FormWindow')['isLive'] ?? null);
    }

    #[Then('the form window :windowId must contain the field :field with the value :value posted to :action')]
    public function theFormWindowMustContainTheFieldWithTheValuePostedTo(
        string $windowId,
        string $field,
        string $value,
        string $action,
    ): void {
        $form = $this->findFormWindow($windowId)->filter('form');
        Assert::assertCount(1, $form);
        Assert::assertSame($action, $form->attr('action'));
        Assert::assertSame('submit->form-window#submit', $form->attr('data-action'));

        $input = $form->filter('input[name="' . $field . '"]');
        Assert::assertCount(1, $input);
        Assert::assertSame($value, (string) $input->attr('value'));
    }

    #[Then('the menu bar must link its brand to :path')]
    public function theMenuBarMustLinkItsBrandTo(string $path): void
    {
        Assert::assertSame($path, $this->getCrawler()->filter('nav.navbar a.navbar-brand')->attr('href'));
    }

    #[Then('the menu bar must show on the :side the sections :sections')]
    public function theMenuBarMustShowOnTheTheSections(string $side, string $sections): void
    {
        $list = match ($side) {
            'left' => 'ul.navbar-nav.me-auto',
            'right' => 'ul.navbar-nav.ms-auto',
            default => Assert::fail("Unknown side $side"),
        };

        Assert::assertSame(
            $this->splitList($sections),
            $this->getCrawler()->filter($list . ' > li > a.dropdown-toggle')->each(
                static fn (Crawler $link): string => $link->text(),
            ),
        );
    }

    #[Then('the section :section of the menu bar must contain the entries :entries')]
    public function theSectionOfTheMenuBarMustContainTheEntries(string $section, string $entries): void
    {
        $sections = $this->getCrawler()->filter('nav.navbar li.nav-item.dropdown')->reduce(
            static fn (Crawler $item): bool => $item->filter('a.dropdown-toggle')->text() === $section,
        );
        Assert::assertCount(1, $sections, "The section $section is not rendered once");

        // A divider is written `---`, a button (an entry handled by a Stimulus controller) `<label> (button)`
        Assert::assertSame(
            $this->splitList($entries),
            $sections->filter('ul.dropdown-menu > li')->each(
                static fn (Crawler $entry): string => match (true) {
                    $entry->filter('hr.dropdown-divider')->count() > 0 => '---',
                    $entry->filter('button')->count() > 0 => $entry->text() . ' (button)',
                    default => $entry->text() . ' (' . $entry->filter('a')->attr('href') . ')',
                },
            ),
        );
    }

    #[When('I choose the theme :body')]
    public function iChooseTheTheme(string $body): void
    {
        $this->iSendTheThemeAs($body, 'application/json');
    }

    /**
     * Sends the request of the `theme` Stimulus controller, with another content type to check the refusals
     */
    #[When('I send the theme :body as :contentType')]
    public function iSendTheThemeAs(string $body, string $contentType): void
    {
        $this->sendRequest(
            method: 'POST',
            path: '/east-ux/theme',
            server: ['CONTENT_TYPE' => $contentType, 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            content: $body,
        );
    }

    #[Then('the page must be rendered with the style :style and the mode :mode')]
    public function thePageMustBeRenderedWithTheStyleAndTheMode(string $style, string $mode): void
    {
        $html = $this->getCrawler()->filter('html');

        Assert::assertSame('theme', $html->attr('data-controller'));
        Assert::assertSame($style, $html->attr('data-theme-style-value'));
        Assert::assertSame($mode, $html->attr('data-theme-mode-value'));
    }

    /**
     * An empty Bootstrap theme means no `data-bs-theme` attribute (set by the browser in the auto mode)
     */
    #[Then('the page must have the classes :classes and the Bootstrap theme :theme')]
    public function thePageMustHaveTheClassesAndTheBootstrapTheme(string $classes, string $theme): void
    {
        $html = $this->getCrawler()->filter('html');

        Assert::assertSame($classes, trim((string) $html->attr('class')));
        Assert::assertSame('' === $theme ? null : $theme, $html->attr('data-bs-theme'));
    }

    #[Then('the choice of the theme must be sent to :url')]
    public function theChoiceOfTheThemeMustBeSentTo(string $url): void
    {
        Assert::assertSame($url, $this->getCrawler()->filter('html')->attr('data-theme-url-value'));
    }

    #[Then('the theme switcher must press the buttons :labels')]
    public function theThemeSwitcherMustPressTheButtons(string $labels): void
    {
        Assert::assertSame(
            $this->splitList($labels),
            $this->getCrawler()->filter('#theme-switcher button[aria-pressed="true"]')->each(
                static fn (Crawler $button): string => $button->text(),
            ),
        );
    }

    #[Then('the menu bar must escape the label :label')]
    public function theMenuBarMustEscapeTheLabel(string $label): void
    {
        $escaped = str_replace(['<', '>'], ['&lt;', '&gt;'], $label);

        Assert::assertStringContainsString($escaped, (string) $this->getCrawler()->filter('nav.navbar')->html());
    }
}
