<?php

declare(strict_types=1);

namespace App\Application\Dto;

use DateTimeImmutable;

final readonly class PurgeOldConversationHistoryResult
{
    public function __construct(
        public int $deletedMessages,
        public int $deletedSessions,
        public DateTimeImmutable $cutoff,
    ) {
    }
}
