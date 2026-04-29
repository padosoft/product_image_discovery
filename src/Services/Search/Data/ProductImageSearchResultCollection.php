<?php

declare(strict_types=1);

namespace Padosoft\ProductImageDiscovery\Services\Search\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

final class ProductImageSearchResultCollection implements Countable, IteratorAggregate
{
    /**
     * @var array<int, ProductImageSearchResult>
     */
    private array $items = [];

    /**
     * @param  iterable<int, ProductImageSearchResult|array<string, mixed>>  $items
     */
    public function __construct(iterable $items = [])
    {
        foreach ($items as $item) {
            $this->items[] = $item instanceof ProductImageSearchResult ? $item : ProductImageSearchResult::fromArray($item);
        }
    }

    public function add(ProductImageSearchResult $result): self
    {
        $clone = clone $this;
        $clone->items[] = $result;

        return $clone;
    }

    /**
     * @return array<int, ProductImageSearchResult>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (ProductImageSearchResult $result): array => $result->toArray(),
            $this->items,
        );
    }

    public function first(): ?ProductImageSearchResult
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
