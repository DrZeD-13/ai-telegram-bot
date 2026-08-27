<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ConversationSession;
use App\Domain\Exception\CoreException;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface ConversationSessionRepository
{
    /**
     * @throws CoreException
     */
    public function findById(Uuid $id): ?ConversationSession;

    /**
     * Most recently active session for this Telegram chat, if any.
     *
     * @throws CoreException
     */
    public function findCurrentByTelegramChatId(int $telegramChatId): ?ConversationSession;

    /**
     * Deletes sessions last used before the cutoff and returns how many rows were removed.
     *
     * @throws CoreException
     */
    public function deleteOlderThan(DateTimeImmutable $cutoff): int;
}
