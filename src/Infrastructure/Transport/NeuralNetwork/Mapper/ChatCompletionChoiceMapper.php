<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Exception\NeuralNetworkTransportException;

final readonly class ChatCompletionChoiceMapper
{
    public function __construct(
        private AssistantMessageMapper $assistantMessageMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $choice
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $choice): ?string
    {
        $message = $choice['message'] ?? null;
        if ($message === null) {
            return null;
        }

        if (!is_array($message)) {
            throw new NeuralNetworkTransportException('Chat completion choice message must be an object.');
        }

        return $this->assistantMessageMapper->map($this->stringKeyed($message));
    }

    /**
     * @param array<mixed> $message
     *
     * @return array<string, mixed>
     *
     * @throws NeuralNetworkTransportException
     */
    private function stringKeyed(array $message): array
    {
        $normalized = [];
        foreach ($message as $key => $value) {
            if (!is_string($key)) {
                throw new NeuralNetworkTransportException('Chat completion choice message is not an object.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
