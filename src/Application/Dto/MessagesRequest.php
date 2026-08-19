<?php

declare(strict_types=1);

namespace App\Application\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class MessagesRequest
{
    public function __construct(
        public string $model,
        public ChatMessageCollection $messages,
        #[SerializedName('max_tokens')]
        public int $maxTokens,
        public bool $stream = false,
    ) {
    }
}
