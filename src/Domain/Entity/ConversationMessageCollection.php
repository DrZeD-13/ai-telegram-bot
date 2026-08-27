<?php

declare(strict_types=1);

namespace App\Domain\Entity;

/**
 * @implements \IteratorAggregate<int, ConversationMessage>
 */
final class ConversationMessageCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var list<ConversationMessage>
     */
    private array $items;

    public function __construct(ConversationMessage ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<ConversationMessage>
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
