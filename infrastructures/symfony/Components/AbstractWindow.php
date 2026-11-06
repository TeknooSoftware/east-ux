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

use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;

/**
 * Live Twig component rendering the abstract window interface.
 *
 * A Live window with a `windowReference` listens to its Mercure topic and runs the methods sent there. The reference is
 * given at the creation of the window and kept for its re-renders: a recipe re-run by a re-render creates a new one,
 * which is ignored, so the topic of the window never changes. The hook must not throw, Symfony UX writes each LiveProp
 * twice when it hydrates a component.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
abstract class AbstractWindow
{
    public string $title = '';
    public bool $closable = false;
    public bool $resizable = false;
    public bool $draggable = false;
    public string $windowId = '';
    public string $closeRedirectUrl = '';
    public string $closeAriaLabel = 'Close';
    public string $resizeAriaLabel = 'Resize';

    /*
     * phpcs 4.0.4 does not tokenize property hook bodies, it reads `$this` as a second property
     * declaration. Only the abstract form (`{ get; }`, used in interfaces) is understood.
     */
    // phpcs:disable PSR2.Classes.PropertyDeclaration.Multiple
    // phpcs:disable PSR2.Classes.PropertyDeclaration.ScopeMissing
    #[LiveProp]
    public ?WindowReference $windowReference = null {
        set => $this->windowReference ?? $value;
    }
    // phpcs:enable PSR2.Classes.PropertyDeclaration.ScopeMissing
    // phpcs:enable PSR2.Classes.PropertyDeclaration.Multiple

    /**
     * Reference of a new window of this class, to call its methods `refresh` and `close` from outside of the page. A
     * window which is not Live has none of them, its reference is refused. A child class can override this method to
     * list its own methods.
     */
    public static function createReference(): WindowReference
    {
        return new WindowReference(
            componentClass: static::class,
            id: (string) Uuid::v7(),
            methods: ['refresh', 'close'],
        );
    }

    public function isClosable(): bool
    {
        return $this->closable;
    }

    public function isResizable(): bool
    {
        return $this->resizable;
    }

    public function isDraggable(): bool
    {
        return $this->draggable;
    }

    public function isFixed(): bool
    {
        return $this->isDraggable() && $this->isClosable();
    }
}
