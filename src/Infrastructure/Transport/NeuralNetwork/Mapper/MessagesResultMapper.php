<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\MessagesResult;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class MessagesResultMapper
{
    public function __construct(
        private MessagesContentBlockMapper $contentBlockMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): MessagesResult
    {
        return new MessagesResult(
            id: $this->optionalString($payload, 'id'),
            text: $this->concatenatedText($payload),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    private function concatenatedText(array $payload): ?string
    {
        $content = $payload['content'] ?? null;
        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content) || $content === []) {
            return null;
        }

        $parts = [];
        foreach ($content as $block) {
            if (!is_array($block)) {
                throw new NeuralNetworkTransportException('Messages content must contain objects.');
            }

            $text = $this->contentBlockMapper->map($this->stringKeyed($block));
            if ($text !== null && $text !== '') {
                $parts[] = $text;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode('', $parts);
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
                throw new NeuralNetworkTransportException('Messages content block is not an object.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
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
            throw new NeuralNetworkTransportException(sprintf('Messages payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
