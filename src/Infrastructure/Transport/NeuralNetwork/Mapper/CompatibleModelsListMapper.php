<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\NeuralNetworkModelCollection;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class CompatibleModelsListMapper
{
    public function __construct(
        private NeuralNetworkModelMapper $modelMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): NeuralNetworkModelCollection
    {
        $items = $payload['data'] ?? [];
        if (!is_array($items)) {
            throw new NeuralNetworkTransportException('Compatible models list payload is not an array.');
        }

        $models = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new NeuralNetworkTransportException('Compatible models list contains a non-object item.');
            }

            $models[] = $this->modelMapper->map($this->stringKeyed($item));
        }

        return new NeuralNetworkModelCollection(...$models);
    }

    /**
     * @param array<mixed> $item
     *
     * @return array<string, mixed>
     *
     * @throws NeuralNetworkTransportException
     */
    private function stringKeyed(array $item): array
    {
        $normalized = [];
        foreach ($item as $key => $value) {
            if (!is_string($key)) {
                throw new NeuralNetworkTransportException('Compatible models list item is not an object.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
