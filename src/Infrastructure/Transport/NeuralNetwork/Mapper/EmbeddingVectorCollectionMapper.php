<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\EmbeddingVectorCollection;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class EmbeddingVectorCollectionMapper
{
    public function __construct(
        private EmbeddingVectorMapper $vectorMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): EmbeddingVectorCollection
    {
        $items = $payload['data'] ?? [];
        if (!is_array($items)) {
            throw new NeuralNetworkTransportException('Embeddings payload data must be an array.');
        }

        $vectors = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new NeuralNetworkTransportException('Embeddings payload contains a non-object item.');
            }

            $vectors[] = $this->vectorMapper->map($this->stringKeyed($item));
        }

        return new EmbeddingVectorCollection(...$vectors);
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
                throw new NeuralNetworkTransportException('Embedding item is not an object.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
