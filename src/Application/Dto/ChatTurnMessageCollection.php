<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * @implements \IteratorAggregate<int, ChatTurnMessage>
 */
final class ChatTurnMessageCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var list<ChatTurnMessage>
     */
    private array $items;

    public function __construct(ChatTurnMessage ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<ChatTurnMessage>
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
