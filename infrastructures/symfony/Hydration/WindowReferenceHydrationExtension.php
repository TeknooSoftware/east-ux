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

namespace Teknoo\East\UXBundle\Hydration;

use InvalidArgumentException;
use Symfony\UX\LiveComponent\Hydration\HydrationExtensionInterface;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;

use function is_a;

/**
 * Hydration of a `WindowReference` in a LiveProp. Symfony UX creates an object without argument and writes its
 * properties, which is impossible for this read-only object. The reference is dehydrated into an array, and not a
 * string: East Foundation's router copies the string props to the attributes of the request. It is rebuilt through its
 * constructor, so it is validated again. Its content is covered by the checksum of the props.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
final class WindowReferenceHydrationExtension implements HydrationExtensionInterface
{
    public function supports(string $className): bool
    {
        return is_a($className, WindowReference::class, true);
    }

    public function hydrate(mixed $value, string $className): WindowReference
    {
        /** @var array{componentClass: string, id: string, methods: list<string>} $value */
        return new WindowReference(
            componentClass: $value['componentClass'],
            id: $value['id'],
            methods: $value['methods'],
        );
    }

    /**
     * @return array{componentClass: string, id: string, methods: list<string>}
     */
    public function dehydrate(object $object): array
    {
        if (!$object instanceof WindowReference) {
            throw new InvalidArgumentException('Only a WindowReference can be dehydrated by this extension', 500);
        }

        return [
            'componentClass' => $object->componentClass,
            'id' => $object->id,
            'methods' => $object->methods,
        ];
    }
}
