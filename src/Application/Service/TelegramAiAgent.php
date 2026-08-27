<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Dto\ChatCompletionRequest;
use App\Application\Dto\ChatMessage;
use App\Application\Dto\ChatMessageCollection;
use App\Application\Dto\ShellCommandResult;
use App\Application\Dto\ToolCall;
use App\Application\Dto\ToolDefinition;
use App\Application\Dto\ToolDefinitionCollection;
use App\Application\Exception\NeuralNetworkException;
use App\Application\Logger\LoggerService;
use App\Application\Port\AiAgent;
use App\Application\Port\NeuralNetworkGateway;
use App\Application\Port\ShellCommandGateway;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs the neural network as an agent: it may call the MCP shell tool repeatedly
 * before producing the final answer for the user.
 */
#[AsAlias(AiAgent::class)]
final readonly class TelegramAiAgent implements AiAgent
{
    public const string SHELL_TOOL_NAME = 'shell';

    private const int MAX_ITERATIONS = 8;

    public function __construct(
        private NeuralNetworkGateway $neuralNetworkGateway,
        private ShellCommandGateway $shellCommandGateway,
        private LoggerService $logger,
        #[Autowire('%env(bool:MCP_SHELL_ENABLED)%')]
        private bool $shellEnabled,
    ) {
    }

    /**
     * @param list<ChatMessage> $conversation ordered messages (system, history, current user)
     *
     * @throws NeuralNetworkException
     */
    public function run(array $conversation, string $modelId): string
    {
        $messages = $conversation;
        $tools = $this->tools();

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; ++$iteration) {
            $result = $this->neuralNetworkGateway->createChatCompletion(new ChatCompletionRequest(
                model: $modelId,
                messages: new ChatMessageCollection(...$messages),
                tools: $tools,
            ));

            if (!$result->hasToolCalls() || $tools === null) {
                return (string) ($result->text ?? '');
            }

            $toolCalls = $result->toolCalls;
            if ($toolCalls === null) {
                return (string) ($result->text ?? '');
            }

            $messages[] = new ChatMessage(
                role: 'assistant',
                content: $result->text,
                toolCalls: $toolCalls,
            );

            foreach ($toolCalls as $toolCall) {
                $messages[] = new ChatMessage(
                    role: 'tool',
                    content: $this->executeToolCall($toolCall),
                    toolCallId: $toolCall->id,
                );
            }
        }

        $final = $this->neuralNetworkGateway->createChatCompletion(new ChatCompletionRequest(
            model: $modelId,
            messages: new ChatMessageCollection(...$messages),
        ));

        return (string) ($final->text ?? '');
    }

    private function tools(): ?ToolDefinitionCollection
    {
        if (!$this->shellEnabled) {
            return null;
        }

        return new ToolDefinitionCollection(new ToolDefinition(
            name: self::SHELL_TOOL_NAME,
            description: 'Выполнить команду в shell (оболочке) хоста и получить stdout, stderr и код возврата. '
                . 'Используй инструмент, когда нужно выполнить команду, посмотреть файлы или узнать состояние системы.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'command' => [
                        'type' => 'string',
                        'description' => 'Команда shell для выполнения через /bin/sh -c.',
                    ],
                ],
                'required' => ['command'],
            ],
        ));
    }

    private function executeToolCall(ToolCall $toolCall): string
    {
        if ($toolCall->name !== self::SHELL_TOOL_NAME) {
            return sprintf('Ошибка: неизвестный инструмент "%s".', $toolCall->name);
        }

        $command = $this->extractCommand($toolCall->arguments);
        if ($command === null) {
            return 'Ошибка: в аргументах инструмента не найдена команда (поле "command").';
        }

        $result = $this->shellCommandGateway->run($command);
        $this->logger->info('MCP shell: команда выполнена', [
            'command' => $command,
            'exitCode' => (string) $result->exitCode,
            'timedOut' => $result->timedOut ? 'true' : 'false',
        ]);

        return $this->formatResult($result);
    }

    private function extractCommand(string $arguments): ?string
    {
        $trimmed = trim($arguments);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $command = $decoded['command'] ?? null;

            return is_string($command) && trim($command) !== '' ? $command : null;
        }

        return $trimmed;
    }

    private function formatResult(ShellCommandResult $result): string
    {
        $parts = [sprintf('exit_code: %d', $result->exitCode)];

        $stdout = trim($result->output);
        $parts[] = 'stdout:' . ($stdout === '' ? ' (пусто)' : "\n" . $stdout);

        $stderr = trim($result->errorOutput);
        if ($stderr !== '') {
            $parts[] = "stderr:\n" . $stderr;
        }

        return implode("\n", $parts);
    }
}
