<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class ChatMessage
{
    public function __construct(
        public string $role,
        public string $content,
    ) {
    }
}
