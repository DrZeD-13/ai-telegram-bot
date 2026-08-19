<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class ChatCompletionRequest
{
    public function __construct(
        public string $model,
        public ChatMessageCollection $messages,
        public bool $stream = false,
    ) {
    }
}
