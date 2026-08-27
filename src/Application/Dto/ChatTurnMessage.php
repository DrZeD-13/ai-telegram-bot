<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class ChatTurnMessage
{
    public function __construct(
        public string $text,
    ) {
    }
}
