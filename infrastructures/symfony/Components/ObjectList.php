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

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

use function is_array;

/**
 * Twig component rendering the object list interface: a table of the objects of `objectsCollection` (the view
 * parameter of East Common's list recipe), each row rendered by the component named in `itemComponent` or by the
 * `list_item` block, with optional headers (translation keys of the application, default domain), or an
 * information message when the collection is empty.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsTwigComponent]
final class ObjectList
{
    /**
     * @var iterable<object>
     */
    public iterable $objectsCollection = [];
    public string $itemComponent = '';

    /**
     * @var array<string>
     */
    public array $tableHeaders = [];

    public function hasObjects(): bool
    {
        if (is_array($this->objectsCollection)) {
            return !empty($this->objectsCollection);
        }

        $count = 0;
        foreach ($this->objectsCollection as $item) {
            $count++;
            break;
        }

        return 0 < $count;
    }
}
