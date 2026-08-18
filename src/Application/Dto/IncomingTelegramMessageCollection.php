<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * @implements \IteratorAggregate<int, IncomingTelegramMessage>
 */
final class IncomingTelegramMessageCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var list<IncomingTelegramMessage>
     */
    private array $items;

    public function __construct(IncomingTelegramMessage ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<IncomingTelegramMessage>
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
