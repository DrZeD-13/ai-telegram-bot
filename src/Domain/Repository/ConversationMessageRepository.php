<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ConversationMessageCollection;
use App\Domain\Exception\CoreException;

interface ConversationMessageRepository
{
    /**
     * Returns the stored dialog history for a chat, ordered from oldest to newest.
     *
     * @throws CoreException
     */
    public function findHistoryByChatId(int $chatId): ConversationMessageCollection;

    /**
     * Removes the whole stored dialog history for a chat and returns how many rows were deleted.
     *
     * @throws CoreException
     */
    public function deleteByChatId(int $chatId): int;
}
