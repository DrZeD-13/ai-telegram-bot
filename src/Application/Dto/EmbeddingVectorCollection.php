<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * @implements \IteratorAggregate<int, EmbeddingVector>
 */
final class EmbeddingVectorCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var list<EmbeddingVector>
     */
    private array $items;

    public function __construct(EmbeddingVector ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<EmbeddingVector>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
