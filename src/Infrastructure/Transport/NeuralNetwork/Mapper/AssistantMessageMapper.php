<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Exception\NeuralNetworkTransportException;

final readonly class AssistantMessageMapper
{
    /**
     * @param array<string, mixed> $message
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $message): ?string
    {
        if (!array_key_exists('content', $message) || $message['content'] === null) {
            return null;
        }

        if (!is_string($message['content'])) {
            throw new NeuralNetworkTransportException('Assistant message content must be a string.');
        }

        return $message['content'];
    }
}
