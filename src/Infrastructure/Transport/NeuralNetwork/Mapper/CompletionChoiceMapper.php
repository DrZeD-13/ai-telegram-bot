<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Exception\NeuralNetworkTransportException;

final readonly class CompletionChoiceMapper
{
    /**
     * @param array<string, mixed> $choice
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $choice): ?string
    {
        if (!array_key_exists('text', $choice) || $choice['text'] === null) {
            return null;
        }

        if (!is_string($choice['text'])) {
            throw new NeuralNetworkTransportException('Completion choice text must be a string.');
        }

        return $choice['text'];
    }
}
