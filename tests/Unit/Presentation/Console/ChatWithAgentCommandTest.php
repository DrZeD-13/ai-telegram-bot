<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Console;

use App\Application\Dto\ChatTurnMessage;
use App\Application\Dto\ChatTurnMessageCollection;
use App\Application\Dto\ChatTurnResult;
use App\Application\Port\ChatTurnHandler;
use App\Presentation\Console\ChatWithAgentCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

#[CoversClass(ChatWithAgentCommand::class)]
#[CoversMethod(ChatWithAgentCommand::class, 'execute')]
final class ChatWithAgentCommandTest extends TestCase
{
    private const string SESSION_A = '018f0000-0000-7000-8000-00000000000a';
    private const string SESSION_B = '018f0000-0000-7000-8000-00000000000b';

    public function testInteractiveTurnThenNewThenContinue(): void
    {
        $handleChatTurn = $this->handleChatTurnMock();
        $handleChatTurn->method('newSessionId')->willReturnOnConsecutiveCalls(
            Uuid::fromString(self::SESSION_A),
            Uuid::fromString(self::SESSION_B),
        );
        $handleChatTurn->method('isResetCommand')->willReturnCallback(
            static fn (string $text): bool => strtolower(trim($text)) === '/new',
        );
        $handleChatTurn->method('isResumeCommand')->willReturn(false);
        $handleChatTurn->method('newSessionNotice')->willReturnCallback(
            static fn (Uuid $previous, Uuid $current): string => 'prev=' . $previous->toRfc4122() . ' curr=' . $current->toRfc4122(),
        );
        $replies = [];
        $handleChatTurn->expects(self::exactly(2))
            ->method('reply')
            ->willReturnCallback(
                static function (Uuid $sessionId, string $text) use (&$replies): ChatTurnResult {
                    $replies[] = [$sessionId->toRfc4122(), $text];

                    return new ChatTurnResult(
                        new ChatTurnMessageCollection(new ChatTurnMessage('ответ')),
                        false,
                        'ответ',
                    );
                },
            );
        $handleChatTurn->expects(self::exactly(2))->method('rememberTurn');

        $tester = new CommandTester(new ChatWithAgentCommand($handleChatTurn));
        $tester->setInputs(['привет', '/new', 'пока', '/exit']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(
            [[self::SESSION_A, 'привет'], [self::SESSION_B, 'пока']],
            $replies,
        );
        $display = $tester->getDisplay();
        self::assertStringContainsString('Сессия: ' . self::SESSION_A, $display);
        self::assertStringContainsString('prev=' . self::SESSION_A, $display);
        self::assertStringContainsString('curr=' . self::SESSION_B, $display);
    }

    public function testUsesCustomSessionId(): void
    {
        $sessionId = Uuid::fromString(self::SESSION_A);
        $handleChatTurn = $this->handleChatTurnMock();
        $handleChatTurn->expects(self::never())->method('newSessionId');
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->method('isResumeCommand')->willReturn(false);
        $handleChatTurn->expects(self::once())
            ->method('reply')
            ->with($sessionId, 'вопрос')
            ->willReturn(new ChatTurnResult(
                new ChatTurnMessageCollection(new ChatTurnMessage('ok')),
                false,
                'ok',
            ));
        $handleChatTurn->expects(self::once())->method('rememberTurn')->with($sessionId, 'вопрос', 'ok');

        $tester = new CommandTester(new ChatWithAgentCommand($handleChatTurn));
        $tester->setInputs(['вопрос', '/exit']);

        $tester->execute(['--session-id' => self::SESSION_A]);
        self::assertStringContainsString('Сессия: ' . self::SESSION_A, $tester->getDisplay());
    }

    public function testOpenCommandSwitchesSessionWithoutCallingAgent(): void
    {
        $handleChatTurn = $this->handleChatTurnMock();
        $handleChatTurn->method('newSessionId')->willReturn(Uuid::fromString(self::SESSION_A));
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->method('isResumeCommand')->willReturn(true);
        $handleChatTurn->method('parseResumeSessionId')->willReturn(Uuid::fromString(self::SESSION_B));
        $handleChatTurn->method('resumeSessionNotice')->willReturn('opened');
        $handleChatTurn->expects(self::never())->method('reply');

        $tester = new CommandTester(new ChatWithAgentCommand($handleChatTurn));
        $tester->setInputs(['/open ' . self::SESSION_B, '/exit']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('opened', $tester->getDisplay());
        self::assertStringNotContainsString(ChatTurnHandler::PROCESSING_NOTICE, $tester->getDisplay());
    }

    public function testDoesNotRememberFailedTurn(): void
    {
        $handleChatTurn = $this->handleChatTurnMock();
        $handleChatTurn->method('newSessionId')->willReturn(Uuid::fromString(self::SESSION_A));
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->method('isResumeCommand')->willReturn(false);
        $handleChatTurn->method('reply')->willReturn(new ChatTurnResult(
            new ChatTurnMessageCollection(new ChatTurnMessage(ChatTurnHandler::ERROR_NEURAL_NETWORK)),
            true,
        ));
        $handleChatTurn->expects(self::never())->method('rememberTurn');

        $tester = new CommandTester(new ChatWithAgentCommand($handleChatTurn));
        $tester->setInputs(['привет', '/exit']);

        $tester->execute([]);
        self::assertStringContainsString(ChatTurnHandler::ERROR_NEURAL_NETWORK, $tester->getDisplay());
    }

    public function testRejectsInvalidSessionIdOption(): void
    {
        $handleChatTurn = $this->handleChatTurnMock();
        $handleChatTurn->expects(self::never())->method('reply');

        $tester = new CommandTester(new ChatWithAgentCommand($handleChatTurn));
        $status = $tester->execute(['--session-id' => '15']);

        self::assertSame(Command::FAILURE, $status);
    }

    private function handleChatTurnMock(): ChatTurnHandler&MockObject
    {
        return $this->createMock(ChatTurnHandler::class);
    }
}
