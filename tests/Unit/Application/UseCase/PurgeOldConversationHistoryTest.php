<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Dto\PurgeOldConversationHistoryResult;
use App\Application\Logger\LoggerService;
use App\Application\UseCase\PurgeOldConversationHistory;
use App\Domain\Repository\ConversationMessageRepository;
use App\Domain\Repository\ConversationSessionRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(PurgeOldConversationHistory::class)]
#[CoversClass(PurgeOldConversationHistoryResult::class)]
#[CoversMethod(PurgeOldConversationHistory::class, 'execute')]
final class PurgeOldConversationHistoryTest extends TestCase
{
    public function testDeletesMessagesAndSessionsOlderThanOneMonth(): void
    {
        $now = new DateTimeImmutable('2026-08-27 08:00:00');
        $expectedCutoff = $now->modify('-1 month');

        $messages = $this->createMock(ConversationMessageRepository::class);
        $messages->expects(self::once())
            ->method('deleteOlderThan')
            ->with(self::equalTo($expectedCutoff))
            ->willReturn(12);

        $sessions = $this->createMock(ConversationSessionRepository::class);
        $sessions->expects(self::once())
            ->method('deleteOlderThan')
            ->with(self::equalTo($expectedCutoff))
            ->willReturn(3);

        $logger = $this->createMock(LoggerService::class);
        $logger->expects(self::once())->method('info');

        $result = (new PurgeOldConversationHistory($messages, $sessions, $logger))->execute($now);

        self::assertSame(12, $result->deletedMessages);
        self::assertSame(3, $result->deletedSessions);
        self::assertEquals($expectedCutoff, $result->cutoff);
    }
}
