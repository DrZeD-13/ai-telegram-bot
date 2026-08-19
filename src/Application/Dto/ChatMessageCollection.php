<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * @implements \IteratorAggregate<int, ChatMessage>
 */
final class ChatMessageCollection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @var list<ChatMessage>
     */
    private array $items;

    public function __construct(ChatMessage ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<ChatMessage>
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
     * @return list<ChatMessage>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
