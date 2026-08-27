<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class ChatCompletionRequest
{
    public function __construct(
        public string $model,
        public ChatMessageCollection $messages,
        public ?ToolDefinitionCollection $tools = null,
        public bool $stream = false,
    ) {
    }
}
