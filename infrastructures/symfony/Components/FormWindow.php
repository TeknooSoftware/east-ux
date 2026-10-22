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

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Teknoo\East\FoundationBundle\UX\Components\LiveTrait;

use function is_object;

/**
 * Live Twig component rendering the form window interface.
 *
 * The form theme applied to the form is given by the `formTheme` prop, by default the `form_theme` value of the
 * `teknoo_east_ux` configuration. When it is null or empty, no theme is set on the form, and the global form themes
 * of the application apply.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsLiveComponent]
final class FormWindow extends AbstractWindow
{
    use LiveTrait;

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

    #[LiveProp(url: true)]
    public ?string $objectId = null;

    public ?FormView $formView = null;

    public object|string|null $objectInstance = null;

    /**
     * @var class-string<FormTypeInterface>|null
     */
    #[LiveProp]
    public ?string $formName = null;

    #[LiveProp]
    public string $formPath = '';

    #[LiveProp(writable: true)]
    public bool $hidden = true;

    #[LiveProp]
    public bool $isLive = true;

    #[LiveProp]
    public ?string $formTheme = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        ?string $formTheme = null,
    ) {
        $this->formTheme = $formTheme;
    }

    #[LiveAction]
    public function close(): void
    {
        $this->hidden = true;
    }

    public function hasSubForms(): bool
    {
        if (null === $this->formView) {
            return false;
        }

        foreach ($this->formView as $child) {
            if ($child->vars['compound'] ?? false) {
                return true;
            }
        }

        return false;
    }

    #[PostMount]
    public function postMount(): void
    {
        if (
            null === $this->formView
            && null !== $this->formName
            && null !== $this->objectInstance
        ) {
            $objectInstance = null;
            if (is_object($this->objectInstance)) {
                $objectInstance = $this->objectInstance;
            }

            $this->formView = $this->formFactory->create($this->formName, $objectInstance)->createView();
        }

        if (!empty($this->formPath)) {
            $this->originalPath = $this->formPath;
        }
    }

    public function __invoke(): void
    {
        $this->postMount();
    }
}
