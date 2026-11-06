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

namespace Teknoo\East\UXBundle\Components;

use LogicException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Teknoo\East\FoundationBundle\UX\Components\LiveTrait;

use function class_exists;
use function is_array;
use function iterator_count;

/**
 * Live Twig component rendering the list window interface.
 *
 * The search form is optional: without `searchFormName`, the window renders no search form (Symfony UX's
 * `ComponentWithFormTrait` still needs a form object, an empty form is used). The "New" button is rendered only when
 * `newFormRoute` is set, its label and the title of the form window it opens come from the `newButtonLabel` and
 * `newFormTitle` props (translated in the application's default domain), or else from the library's catalogue.
 * The window props of `AbstractWindow` are redeclared as LiveProps, so a Live re-render keeps them.
 * A floating window (`floating`, a window loaded by the component renderer) is positioned over the page, and its page
 * is not bound to the URL: the URL stays the one of the page. `hidden` is writable, the browser shows the window by
 * setting it to false when it opens it.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsLiveComponent]
final class ListWindow extends AbstractWindow
{
    use LiveTrait;
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    #[LiveProp]
    public string $title = '';

    #[LiveProp]
    public bool $closable = false;

    #[LiveProp]
    public bool $resizable = false;

    #[LiveProp]
    public bool $draggable = false;

    #[LiveProp]
    public string $closeRedirectUrl = '';

    #[LiveProp]
    public string $closeAriaLabel = 'Close';

    #[LiveProp]
    public string $resizeAriaLabel = 'Resize';

    #[LiveProp]
    public string $windowId = '';

    #[LiveProp(writable: true)]
    public bool $hidden = false;

    #[LiveProp]
    public bool $floating = false;

    /**
     * @var iterable<object>
     */
    public iterable $objectsCollection = [];
    public int $pageCount = 0;
    #[LiveProp(writable: true, url: true, modifier: 'modifyPage')]
    public int $page = 1;
    #[LiveProp]
    public string $itemComponent = '';

    /**
     * @var array<string>
     */
    #[LiveProp]
    public array $tableHeaders = [];

    /**
     * @var class-string<FormTypeInterface>|null
     */
    #[LiveProp]
    public ?string $searchFormName = null;
    #[LiveProp]
    public string $newFormName = '';
    #[LiveProp]
    public string $newFormRoute = '';
    #[LiveProp]
    public string $newButtonLabel = '';
    #[LiveProp]
    public string $newFormTitle = '';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly FormFactoryInterface $formFactory,
    ) {
        $this->updateOriginalPath();
    }

    /**
     * @return FormInterface<mixed>
     */
    protected function instantiateForm(): FormInterface
    {
        if (empty($this->searchFormName)) {
            // No search form: ComponentWithFormTrait still needs a form, an empty one, never rendered
            return $this->formFactory->create(FormType::class);
        }

        if (!class_exists($this->searchFormName)) {
            throw new LogicException('searchFormName must be a valid form class');
        }

        return $this->formFactory->create($this->searchFormName);
    }

    /**
     * Modifier of the LiveProp `page`, a contract of Symfony UX (`LivePropMetadata::withModifier()`), which needs the
     * modified LiveProp as returned value: the page of a floating window is not bound to the URL, the URL of the page
     * holding it stays unchanged.
     */
    public function modifyPage(LiveProp $liveProp): LiveProp
    {
        if ($this->floating) {
            return $liveProp->withUrl(false);
        }

        return $liveProp;
    }

    /*
     * Nothing to do: the recipe of the window has already run again, the window is rendered with its new objects.
     */
    #[LiveAction]
    public function refresh(): void
    {
    }

    #[LiveAction]
    public function close(): void
    {
        $this->hidden = true;
    }

    public function hasSearchForm(): bool
    {
        return !empty($this->searchFormName);
    }

    public function getCurrentRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    public function hasObjects(): bool
    {
        if (is_array($this->objectsCollection)) {
            return !empty($this->objectsCollection);
        }

        return iterator_count($this->objectsCollection) > 0;
    }

    public function hasPagination(): bool
    {
        return $this->pageCount > 1;
    }
}
