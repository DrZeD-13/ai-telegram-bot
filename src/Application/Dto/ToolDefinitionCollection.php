<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * @implements \IteratorAggregate<int, ToolDefinition>
 */
final class ToolDefinitionCollection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @var list<ToolDefinition>
     */
    private array $items;

    public function __construct(ToolDefinition ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<ToolDefinition>
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
     * @return list<ToolDefinition>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
