<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ConversationMessageCollection;
use App\Domain\Exception\CoreException;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface ConversationMessageRepository
{
    /**
     * Returns the stored dialog history for a session, ordered from oldest to newest.
     *
     * @throws CoreException
     */
    public function findHistoryByChatId(Uuid $chatId): ConversationMessageCollection;

    /**
     * Deletes stored messages created before the cutoff and returns how many rows were removed.
     *
     * @throws CoreException
     */
    public function deleteOlderThan(DateTimeImmutable $cutoff): int;
}
