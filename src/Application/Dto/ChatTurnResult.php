<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class ChatTurnResult
{
    public function __construct(
        public ChatTurnMessageCollection $messages,
        public bool $failed,
        public ?string $assistantText = null,
    ) {
    }
}
