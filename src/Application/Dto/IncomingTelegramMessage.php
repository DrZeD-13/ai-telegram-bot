<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class IncomingTelegramMessage
{
    public function __construct(
        public int $updateId,
        public int $messageId,
        public ?TelegramUser $from,
        public TelegramChat $chat,
        public int $date,
        public ?string $text,
    ) {
    }
}
