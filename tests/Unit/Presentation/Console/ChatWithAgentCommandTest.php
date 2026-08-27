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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ChatWithAgentCommand::class)]
#[CoversMethod(ChatWithAgentCommand::class, 'execute')]
final class ChatWithAgentCommandTest extends TestCase
{
    public function testInteractiveTurnThenResetThenExit(): void
    {
        $handleChatTurn = $this->createMock(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturnCallback(
            static fn (string $text): bool => strtolower(trim($text)) === '/new',
        );
        $handleChatTurn->expects(self::once())
            ->method('reply')
            ->with(0, 'привет')
            ->willReturn(new ChatTurnResult(
                new ChatTurnMessageCollection(new ChatTurnMessage('ответ агента')),
                false,
                'ответ агента',
            ));
        $handleChatTurn->expects(self::once())->method('rememberTurn')->with(0, 'привет', 'ответ агента');
        $handleChatTurn->expects(self::once())->method('resetSession')->with(0);

        $tester = new CommandTester(new ChatWithAgentCommand($handleChatTurn));
        $tester->setInputs(['привет', '/new', '/exit']);

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        $display = $tester->getDisplay();
        self::assertStringContainsString(ChatTurnHandler::PROCESSING_NOTICE, $display);
        self::assertStringContainsString('ответ агента', $display);
        self::assertStringContainsString(ChatTurnHandler::RESET_NOTICE, $display);
    }

    public function testUsesCustomSessionId(): void
    {
        $handleChatTurn = $this->createMock(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturn(false);
        $handleChatTurn->expects(self::once())
            ->method('reply')
            ->with(15, 'вопрос')
            ->willReturn(new ChatTurnResult(
                new ChatTurnMessageCollection(new ChatTurnMessage('ok')),
                false,
                'ok',
            ));
        $handleChatTurn->expects(self::once())->method('rememberTurn')->with(15, 'вопрос', 'ok');

        $tester = new CommandTester(new ChatWithAgentCommand($handleChatTurn));
        $tester->setInputs(['вопрос', '/exit']);

        $tester->execute(['--session-id' => '15']);
    }

    public function testDoesNotRememberFailedTurn(): void
    {
        $handleChatTurn = $this->createMock(ChatTurnHandler::class);
        $handleChatTurn->method('isResetCommand')->willReturn(false);
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
}
