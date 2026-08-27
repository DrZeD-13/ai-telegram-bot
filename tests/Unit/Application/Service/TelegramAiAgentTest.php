<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Dto\ChatCompletionRequest;
use App\Application\Dto\ChatCompletionResult;
use App\Application\Dto\ChatMessage;
use App\Application\Dto\ShellCommandResult;
use App\Application\Dto\ToolCall;
use App\Application\Dto\ToolCallCollection;
use App\Application\Logger\LoggerService;
use App\Application\Port\NeuralNetworkGateway;
use App\Application\Port\ShellCommandGateway;
use App\Application\Service\TelegramAiAgent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramAiAgent::class)]
#[CoversMethod(TelegramAiAgent::class, 'run')]
final class TelegramAiAgentTest extends TestCase
{
    public function testDirectAnswerDoesNotCallShell(): void
    {
        $neuralNetworkGateway = $this->createMock(NeuralNetworkGateway::class);
        $neuralNetworkGateway->expects(self::once())
            ->method('createChatCompletion')
            ->with(self::callback(static function (ChatCompletionRequest $request): bool {
                self::assertSame('model-1', $request->model);
                self::assertNotNull($request->tools);
                self::assertCount(1, $request->tools);
                self::assertSame(TelegramAiAgent::SHELL_TOOL_NAME, $request->tools->all()[0]->name);

                return true;
            }))
            ->willReturn(new ChatCompletionResult('id', 'готовый ответ'));

        $shell = $this->createMock(ShellCommandGateway::class);
        $shell->expects(self::never())->method('run');

        $answer = $this->agent($neuralNetworkGateway, $shell)->run(
            [new ChatMessage('user', 'привет')],
            'model-1',
        );

        self::assertSame('готовый ответ', $answer);
    }

    public function testShellToolCallIsExecutedAndFedBack(): void
    {
        $neuralNetworkGateway = $this->createStub(NeuralNetworkGateway::class);
        $neuralNetworkGateway->method('createChatCompletion')->willReturnOnConsecutiveCalls(
            new ChatCompletionResult(
                id: 'id-1',
                text: null,
                toolCalls: new ToolCallCollection(new ToolCall(
                    id: 'call_1',
                    name: TelegramAiAgent::SHELL_TOOL_NAME,
                    arguments: '{"command":"echo hello"}',
                )),
            ),
            new ChatCompletionResult('id-2', 'команда выполнена'),
        );

        $shell = $this->createMock(ShellCommandGateway::class);
        $shell->expects(self::once())
            ->method('run')
            ->with('echo hello')
            ->willReturn(new ShellCommandResult(
                command: 'echo hello',
                output: "hello\n",
                errorOutput: '',
                exitCode: 0,
            ));

        $answer = $this->agent($neuralNetworkGateway, $shell)->run(
            [new ChatMessage('user', 'скажи hello')],
            'model-1',
        );

        self::assertSame('команда выполнена', $answer);
    }

    public function testDisabledShellOmitsToolsAndIgnoresToolCalls(): void
    {
        $neuralNetworkGateway = $this->createMock(NeuralNetworkGateway::class);
        $neuralNetworkGateway->expects(self::once())
            ->method('createChatCompletion')
            ->with(self::callback(static function (ChatCompletionRequest $request): bool {
                self::assertNull($request->tools);

                return true;
            }))
            ->willReturn(new ChatCompletionResult(
                id: 'id-1',
                text: 'без инструментов',
                toolCalls: new ToolCallCollection(new ToolCall('call_1', 'shell', '{"command":"ls"}')),
            ));

        $shell = $this->createMock(ShellCommandGateway::class);
        $shell->expects(self::never())->method('run');

        $answer = $this->agent($neuralNetworkGateway, $shell, shellEnabled: false)->run(
            [new ChatMessage('user', 'ls')],
            'model-1',
        );

        self::assertSame('без инструментов', $answer);
    }

    public function testUnknownToolNameIsReportedWithoutCallingShell(): void
    {
        $neuralNetworkGateway = $this->createStub(NeuralNetworkGateway::class);
        $neuralNetworkGateway->method('createChatCompletion')->willReturnOnConsecutiveCalls(
            new ChatCompletionResult(
                id: 'id-1',
                text: null,
                toolCalls: new ToolCallCollection(new ToolCall('call_x', 'search', '{}')),
            ),
            new ChatCompletionResult('id-2', 'неизвестный инструмент'),
        );

        $shell = $this->createMock(ShellCommandGateway::class);
        $shell->expects(self::never())->method('run');

        $answer = $this->agent($neuralNetworkGateway, $shell)->run(
            [new ChatMessage('user', 'найди')],
            'model-1',
        );

        self::assertSame('неизвестный инструмент', $answer);
    }

    public function testPlainCommandArgumentsAreAccepted(): void
    {
        $neuralNetworkGateway = $this->createStub(NeuralNetworkGateway::class);
        $neuralNetworkGateway->method('createChatCompletion')->willReturnOnConsecutiveCalls(
            new ChatCompletionResult(
                id: 'id-1',
                text: null,
                toolCalls: new ToolCallCollection(new ToolCall('call_1', 'shell', 'pwd')),
            ),
            new ChatCompletionResult('id-2', 'ok'),
        );

        $shell = $this->createMock(ShellCommandGateway::class);
        $shell->expects(self::once())->method('run')->with('pwd')->willReturn(new ShellCommandResult(
            command: 'pwd',
            output: '/tmp',
            errorOutput: '',
            exitCode: 0,
        ));

        $answer = $this->agent($neuralNetworkGateway, $shell)->run(
            [new ChatMessage('user', 'pwd')],
            'model-1',
        );

        self::assertSame('ok', $answer);
    }

    private function agent(
        NeuralNetworkGateway $neuralNetworkGateway,
        ShellCommandGateway $shellCommandGateway,
        bool $shellEnabled = true,
    ): TelegramAiAgent {
        return new TelegramAiAgent(
            $neuralNetworkGateway,
            $shellCommandGateway,
            $this->createStub(LoggerService::class),
            $shellEnabled,
        );
    }
}
