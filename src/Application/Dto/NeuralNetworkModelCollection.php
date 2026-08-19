<?php

declare(strict_types=1);

namespace App\Application\Dto;

/**
 * @implements \IteratorAggregate<int, NeuralNetworkModel>
 */
final class NeuralNetworkModelCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var list<NeuralNetworkModel>
     */
    private array $items;

    public function __construct(NeuralNetworkModel ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return list<NeuralNetworkModel>
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
