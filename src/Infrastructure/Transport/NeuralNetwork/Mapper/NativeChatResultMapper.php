<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\NativeChatResult;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class NativeChatResultMapper
{
    public function __construct(
        private ChatCompletionChoiceMapper $choiceMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): NativeChatResult
    {
        return new NativeChatResult(
            id: $this->optionalString($payload, 'id'),
            text: $this->assistantText($payload),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    private function assistantText(array $payload): ?string
    {
        foreach (['output', 'content'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                if (!is_string($payload[$key])) {
                    throw new NeuralNetworkTransportException(sprintf('Native chat payload has a non-string %s.', $key));
                }

                return $payload[$key];
            }
        }

        $choices = $payload['choices'] ?? null;
        if (!is_array($choices) || $choices === []) {
            return null;
        }

        $choice = $choices[0] ?? null;
        if (!is_array($choice)) {
            throw new NeuralNetworkTransportException('Native chat choices must contain objects.');
        }

        return $this->choiceMapper->map($this->stringKeyed($choice));
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
                throw new NeuralNetworkTransportException('Native chat choice is not an object.');
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
            throw new NeuralNetworkTransportException(sprintf('Native chat payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
