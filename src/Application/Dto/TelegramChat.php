<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class TelegramChat
{
    public function __construct(
        public int $id,
        public string $type,
        public ?string $title,
        public ?string $username,
        public ?string $firstName,
        public ?string $lastName,
        public ?bool $isForum,
        public ?bool $isDirectMessages,
    ) {
    }
}
