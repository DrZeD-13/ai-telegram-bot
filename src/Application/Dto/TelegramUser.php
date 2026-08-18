<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class TelegramUser
{
    public function __construct(
        public int $id,
        public bool $isBot,
        public string $firstName,
        public ?string $lastName,
        public ?string $username,
        public ?string $languageCode,
        public ?bool $isPremium,
        public ?bool $addedToAttachmentMenu,
    ) {
    }
}
