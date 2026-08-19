<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Exception\NeuralNetworkTransportException;

final readonly class ResponseOutputTextMapper
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): ?string
    {
        if (array_key_exists('output_text', $payload) && $payload['output_text'] !== null) {
            if (!is_string($payload['output_text'])) {
                throw new NeuralNetworkTransportException('Response payload has a non-string output_text.');
            }

            return $payload['output_text'];
        }

        $output = $payload['output'] ?? null;
        if (!is_array($output) || $output === []) {
            return null;
        }

        $first = $output[0] ?? null;
        if (!is_array($first)) {
            throw new NeuralNetworkTransportException('Response output items must be objects.');
        }

        $content = $first['content'] ?? null;
        if (!is_array($content) || $content === []) {
            return null;
        }

        $block = $content[0] ?? null;
        if (!is_array($block)) {
            throw new NeuralNetworkTransportException('Response output content must contain objects.');
        }

        $text = $block['text'] ?? null;
        if ($text === null) {
            return null;
        }

        if (!is_string($text)) {
            throw new NeuralNetworkTransportException('Response output text must be a string.');
        }

        return $text;
    }
}
