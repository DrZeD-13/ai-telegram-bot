<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Exception\CoreException;

interface ProcessedTelegramMessageRepository
{
    /**
     * @throws CoreException
     */
    public function findMaxUpdateId(): ?int;

    /**
     * @throws CoreException
     */
    public function findOneByChatAndMessageId(int $chatId, int $messageId): ?ProcessedTelegramMessage;
}
