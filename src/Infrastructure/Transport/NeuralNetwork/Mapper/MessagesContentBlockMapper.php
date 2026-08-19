<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Exception\NeuralNetworkTransportException;

final readonly class MessagesContentBlockMapper
{
    /**
     * @param array<string, mixed> $block
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $block): ?string
    {
        if (!array_key_exists('text', $block) || $block['text'] === null) {
            return null;
        }

        if (!is_string($block['text'])) {
            throw new NeuralNetworkTransportException('Messages content block text must be a string.');
        }

        return $block['text'];
    }
}
