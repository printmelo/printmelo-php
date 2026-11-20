<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * One page of a list. Loop over it, or pass `$page->nextCursor` as `cursor` for the next page.
 *
 * @template T of Resource
 *
 * @implements \IteratorAggregate<int, T>
 */
final class CursorPage implements \IteratorAggregate, \Countable
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
    ) {
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null && $this->nextCursor !== '';
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
