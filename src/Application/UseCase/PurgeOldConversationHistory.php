<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Dto\PurgeOldConversationHistoryResult;
use App\Application\Logger\LoggerService;
use App\Domain\Exception\CoreException;
use App\Domain\Repository\ConversationMessageRepository;
use App\Domain\Repository\ConversationSessionRepository;
use DateTimeImmutable;
use DateTimeInterface;

final class PurgeOldConversationHistory
{
    public const string RETENTION = '-1 month';

    public function __construct(
        private readonly ConversationMessageRepository $conversationMessageRepository,
        private readonly ConversationSessionRepository $conversationSessionRepository,
        private readonly LoggerService $logger,
    ) {
    }

    /**
     * @throws CoreException
     */
    public function execute(?DateTimeImmutable $now = null): PurgeOldConversationHistoryResult
    {
        $cutoff = ($now ?? new DateTimeImmutable())->modify(self::RETENTION);
        $deletedMessages = $this->conversationMessageRepository->deleteOlderThan($cutoff);
        $deletedSessions = $this->conversationSessionRepository->deleteOlderThan($cutoff);

        $this->logger->info('Очищена история диалогов старше срока хранения', [
            'cutoff' => $cutoff->format(DateTimeInterface::ATOM),
            'deletedMessages' => (string) $deletedMessages,
            'deletedSessions' => (string) $deletedSessions,
        ]);

        return new PurgeOldConversationHistoryResult($deletedMessages, $deletedSessions, $cutoff);
    }
}
