<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\ToolCall;
use App\Application\Dto\ToolCallCollection;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class ToolCallCollectionMapper
{
    /**
     * @param array<string, mixed> $choice
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $choice): ?ToolCallCollection
    {
        $message = $choice['message'] ?? null;
        if (!is_array($message)) {
            return null;
        }

        $rawCalls = $message['tool_calls'] ?? null;
        if (!is_array($rawCalls) || $rawCalls === []) {
            return null;
        }

        $calls = [];
        foreach ($rawCalls as $rawCall) {
            if (!is_array($rawCall)) {
                throw new NeuralNetworkTransportException('Tool call must be an object.');
            }

            $calls[] = $this->mapCall($rawCall);
        }

        return new ToolCallCollection(...$calls);
    }

    /**
     * @param array<mixed> $rawCall
     *
     * @throws NeuralNetworkTransportException
     */
    private function mapCall(array $rawCall): ToolCall
    {
        $function = $rawCall['function'] ?? null;
        if (!is_array($function)) {
            throw new NeuralNetworkTransportException('Tool call is missing the function object.');
        }

        $name = $function['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new NeuralNetworkTransportException('Tool call function name must be a non-empty string.');
        }

        return new ToolCall(
            id: $this->callId($rawCall, $name),
            name: $name,
            arguments: $this->arguments($function),
        );
    }

    /**
     * @param array<mixed> $rawCall
     */
    private function callId(array $rawCall, string $name): string
    {
        $id = $rawCall['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return $id;
        }

        return 'call_' . substr(md5($name . serialize($rawCall)), 0, 12);
    }

    /**
     * @param array<mixed> $function
     *
     * @throws NeuralNetworkTransportException
     */
    private function arguments(array $function): string
    {
        $arguments = $function['arguments'] ?? '';
        if (is_string($arguments)) {
            return $arguments;
        }

        if (is_array($arguments)) {
            $encoded = json_encode($arguments);
            if ($encoded === false) {
                throw new NeuralNetworkTransportException('Tool call arguments could not be encoded.');
            }

            return $encoded;
        }

        throw new NeuralNetworkTransportException('Tool call arguments must be a string or object.');
    }
}
