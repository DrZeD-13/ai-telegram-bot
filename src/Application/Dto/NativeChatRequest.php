<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class NativeChatRequest
{
    public function __construct(
        public string $model,
        public ChatMessageCollection $messages,
    ) {
    }
}
