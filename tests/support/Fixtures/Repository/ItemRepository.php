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

namespace Teknoo\Tests\East\UX\Support\Fixtures\Repository;

use DomainException;
use Teknoo\East\Common\Contracts\DBSource\RepositoryInterface;
use Teknoo\Recipe\Promise\PromiseInterface;
use Teknoo\Tests\East\UX\Support\Fixtures\Object\Item;

use function array_slice;
use function array_values;
use function count;
use function is_string;

/**
 * In-memory repository of `Item` objects, in their insertion order, for the test application. Criteria are ignored,
 * except `id` in `findOneBy()`.
 *
 * @implements RepositoryInterface<Item>
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ItemRepository implements RepositoryInterface
{
    /**
     * @var array<string, Item>
     */
    private array $items = [];

    public function add(Item $item): self
    {
        if (!$item->hasId()) {
            $item->setId('item-' . (count($this->items) + 1));
        }

        $this->items[$item->getId()] = $item;

        return $this;
    }

    public function remove(Item $item): self
    {
        unset($this->items[$item->getId()]);

        return $this;
    }

    public function find(string $id, PromiseInterface $promise, array $hydrate = []): RepositoryInterface
    {
        if (isset($this->items[$id])) {
            $promise->success($this->items[$id]);
        } else {
            $promise->fail(new DomainException("Item $id not found", 404));
        }

        return $this;
    }

    public function findAll(PromiseInterface $promise, array $hydrate = []): RepositoryInterface
    {
        $promise->success(array_values($this->items));

        return $this;
    }

    public function findBy(
        array $criteria,
        PromiseInterface $promise,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        array $hydrate = [],
    ): RepositoryInterface {
        $promise->success(array_slice(array_values($this->items), (int) $offset, $limit));

        return $this;
    }

    public function distinctBy(
        string $field,
        array $criteria,
        PromiseInterface $promise,
        ?int $limit = null,
        ?int $offset = null,
    ): RepositoryInterface {
        $promise->success([]);

        return $this;
    }

    public function count(array $criteria, PromiseInterface $promise): RepositoryInterface
    {
        $promise->success(count($this->items));

        return $this;
    }

    public function findOneBy(array $criteria, PromiseInterface $promise, array $hydrate = []): RepositoryInterface
    {
        if (is_string($criteria['id'] ?? null)) {
            return $this->find($criteria['id'], $promise, $hydrate);
        }

        $promise->fail(new DomainException('Item not found', 404));

        return $this;
    }
}
