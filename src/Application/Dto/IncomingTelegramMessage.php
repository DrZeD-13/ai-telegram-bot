<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class IncomingTelegramMessage
{
    public function __construct(
        public int $updateId,
        public int|string $chatId,
        public int $messageId,
        public string $text,
    ) {
    }
}
