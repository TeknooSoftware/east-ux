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

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Teknoo\East\FoundationBundle\UX\Components\LiveTrait;

/**
 * Live window of teknoo/east-ux showing one persisted object, through the component named by `contentComponent`,
 * which gets the object in its prop `object`. It is the window of the pages of an object, and of the links of the rows
 * of their lists, loaded by the component renderer.
 *
 * - The object is loaded by the recipe of the route of `originalPath`, behind its access control: `objectInstance` is
 *   a public property, not a LiveProp, filled by East Common's `LiveComponentBuilder` from the view parameter of the
 *   same name. It is null in a window rendered by the component renderer and after a refusal: the window is empty.
 * - The window props of `AbstractWindow` are redeclared as LiveProps, so a Live re-render keeps them, as `ListWindow`.
 * - A floating window (`floating`, a window loaded by the component renderer) is positioned over the page. `hidden` is
 *   writable: the browser shows the window by setting it to false when it opens it (`list-window-refresh`).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsLiveComponent]
final class ObjectWindow extends AbstractWindow
{
    use LiveTrait;
    use DefaultActionTrait;

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

    #[LiveProp]
    public string $contentComponent = '';

    public ?object $objectInstance = null;

    /*
     * Nothing to do: the recipe of the window has already run again, the window is rendered with its object.
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
}
