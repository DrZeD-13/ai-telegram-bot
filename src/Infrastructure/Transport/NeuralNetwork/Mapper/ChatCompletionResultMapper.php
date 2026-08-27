<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\ChatCompletionResult;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class ChatCompletionResultMapper
{
    public function __construct(
        private ChatCompletionChoiceMapper $choiceMapper,
        private ToolCallCollectionMapper $toolCallCollectionMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): ChatCompletionResult
    {
        $choice = $this->firstChoice($payload);

        return new ChatCompletionResult(
            id: $this->optionalString($payload, 'id'),
            text: $choice === null ? null : $this->choiceMapper->map($choice),
            toolCalls: $choice === null ? null : $this->toolCallCollectionMapper->map($choice),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     *
     * @throws NeuralNetworkTransportException
     */
    private function firstChoice(array $payload): ?array
    {
        $choices = $payload['choices'] ?? [];
        if (!is_array($choices) || $choices === []) {
            return null;
        }

        $choice = $choices[0] ?? null;
        if (!is_array($choice)) {
            throw new NeuralNetworkTransportException('Chat completion choices must contain objects.');
        }

        return $this->stringKeyed($choice);
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
                throw new NeuralNetworkTransportException('Chat completion choice is not an object.');
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
            throw new NeuralNetworkTransportException(sprintf('Chat completion payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
