<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\LoadModelResult;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class LoadModelResultMapper
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): LoadModelResult
    {
        return new LoadModelResult(
            status: $this->optionalString($payload, 'status') ?? $this->optionalString($payload, 'type'),
            message: $this->optionalString($payload, 'message'),
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
            throw new NeuralNetworkTransportException(sprintf('Load model payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
