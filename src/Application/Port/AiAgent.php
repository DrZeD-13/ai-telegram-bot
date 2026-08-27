<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Dto\ChatMessage;
use App\Application\Exception\NeuralNetworkException;

interface AiAgent
{
    /**
     * Runs the neural network agent over the given conversation and returns the final answer text.
     *
     * The agent may call tools (such as the MCP shell) any number of times before answering.
     *
     * @param list<ChatMessage> $conversation ordered messages (system, history, current user)
     *
     * @throws NeuralNetworkException
     */
    public function run(array $conversation, string $modelId): string;
}
