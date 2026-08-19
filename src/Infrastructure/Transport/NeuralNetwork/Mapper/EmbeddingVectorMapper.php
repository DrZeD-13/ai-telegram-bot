<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\EmbeddingVector;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class EmbeddingVectorMapper
{
    /**
     * @param array<string, mixed> $item
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $item): EmbeddingVector
    {
        $embedding = $item['embedding'] ?? null;
        if (!is_array($embedding)) {
            throw new NeuralNetworkTransportException('Embedding item is missing a vector.');
        }

        $values = [];
        foreach ($embedding as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new NeuralNetworkTransportException('Embedding vector must contain only numbers.');
            }

            $values[] = (float) $value;
        }

        return new EmbeddingVector($values);
    }
}
