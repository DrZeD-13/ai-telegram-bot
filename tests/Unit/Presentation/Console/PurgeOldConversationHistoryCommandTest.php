<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Console;

use App\Application\Logger\LoggerService;
use App\Application\UseCase\PurgeOldConversationHistory;
use App\Domain\Exception\CoreException;
use App\Domain\Repository\ConversationMessageRepository;
use App\Domain\Repository\ConversationSessionRepository;
use App\Presentation\Console\PurgeOldConversationHistoryCommand;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(PurgeOldConversationHistoryCommand::class)]
#[CoversClass(PurgeOldConversationHistory::class)]
#[CoversMethod(PurgeOldConversationHistoryCommand::class, 'execute')]
final class PurgeOldConversationHistoryCommandTest extends TestCase
{
    public function testReportsDeletedCounts(): void
    {
        $messages = $this->createMock(ConversationMessageRepository::class);
        $messages->expects(self::once())
            ->method('deleteOlderThan')
            ->with(self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(4);

        $sessions = $this->createMock(ConversationSessionRepository::class);
        $sessions->expects(self::once())->method('deleteOlderThan')->willReturn(1);

        $tester = new CommandTester(new PurgeOldConversationHistoryCommand(
            new PurgeOldConversationHistory($messages, $sessions, $this->createStub(LoggerService::class)),
            $this->lockFactory(),
        ));
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Удалено сообщений: 4', $tester->getDisplay());
        self::assertStringContainsString('сессий: 1', $tester->getDisplay());
    }

    public function testFailsWhenUseCaseThrows(): void
    {
        $messages = $this->createMock(ConversationMessageRepository::class);
        $messages->expects(self::once())
            ->method('deleteOlderThan')
            ->willThrowException(new CoreException('сбой БД'));

        $sessions = $this->createMock(ConversationSessionRepository::class);
        $sessions->expects(self::never())->method('deleteOlderThan');

        $tester = new CommandTester(new PurgeOldConversationHistoryCommand(
            new PurgeOldConversationHistory($messages, $sessions, $this->createStub(LoggerService::class)),
            $this->lockFactory(),
        ));
        $status = $tester->execute([]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('сбой БД', $tester->getDisplay());
    }

    private function lockFactory(): LockFactory
    {
        $store = new InMemoryStore();
        self::assertInstanceOf(SharedLockStoreInterface::class, $store);

        return new LockFactory($store);
    }
}
