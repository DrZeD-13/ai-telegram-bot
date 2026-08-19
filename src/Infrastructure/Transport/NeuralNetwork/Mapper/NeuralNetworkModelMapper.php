<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\NeuralNetworkModel;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class NeuralNetworkModelMapper
{
    /**
     * @param array<string, mixed> $model
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $model): NeuralNetworkModel
    {
        $id = $model['id'] ?? $model['key'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new NeuralNetworkTransportException('Neural network model payload is missing a valid id.');
        }

        return new NeuralNetworkModel(
            id: $id,
            object: $this->optionalString($model, 'object'),
            ownedBy: $this->optionalString($model, 'owned_by') ?? $this->optionalString($model, 'ownedBy'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    private function optionalString(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        if (!is_string($payload[$key])) {
            throw new NeuralNetworkTransportException(sprintf('Neural network model payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
