<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\ResponseResult;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class ResponseResultMapper
{
    public function __construct(
        private ResponseOutputTextMapper $outputTextMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): ResponseResult
    {
        return new ResponseResult(
            id: $this->optionalString($payload, 'id'),
            text: $this->outputTextMapper->map($payload),
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
            throw new NeuralNetworkTransportException(sprintf('Response payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
