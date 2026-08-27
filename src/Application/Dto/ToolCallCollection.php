<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * @implements \IteratorAggregate<int, ToolCall>
 */
final class ToolCallCollection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @var list<ToolCall>
     */
    private array $items;

    public function __construct(ToolCall ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<ToolCall>
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

    /**
     * @return list<ToolCall>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
