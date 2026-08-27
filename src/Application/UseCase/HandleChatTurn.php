<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Dto\ChatMessage;
use App\Application\Dto\ChatTurnMessage;
use App\Application\Dto\ChatTurnMessageCollection;
use App\Application\Dto\ChatTurnResult;
use App\Application\Exception\NeuralNetworkException;
use App\Application\Exception\PersistenceException;
use App\Application\Logger\LoggerService;
use App\Application\Port\AiAgent;
use App\Application\Port\ChatTurnHandler;
use App\Application\Port\NeuralNetworkGateway;
use App\Application\Port\UnitOfWork;
use App\Application\Service\TelegramMessageSplitter;
use App\Domain\Entity\ConversationMessage;
use App\Domain\Exception\CoreException;
use App\Domain\Repository\ConversationMessageRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Shared dialog turn for Telegram and the console: history, MCP agent, /new, split replies.
 */
#[AsAlias(ChatTurnHandler::class)]
final class HandleChatTurn implements ChatTurnHandler
{
    public const string RESET_COMMAND = '/new';

    private const string SYSTEM_PROMPT = 'Ты — полезный ИИ-агент в Telegram. '
        . 'У тебя есть инструмент shell для выполнения команд в оболочке хоста — используй его, когда для ответа '
        . 'нужно выполнить команду или проверить состояние системы. Отвечай пользователю на русском языке.';

    public function __construct(
        private readonly NeuralNetworkGateway $neuralNetworkGateway,
        private readonly ConversationMessageRepository $conversationMessageRepository,
        private readonly AiAgent $agent,
        private readonly TelegramMessageSplitter $splitter,
        private readonly UnitOfWork $unitOfWork,
        private readonly LoggerService $logger,
    ) {
    }

    public function isResetCommand(string $text): bool
    {
        $normalized = strtolower(trim($text));

        return $normalized === self::RESET_COMMAND || str_starts_with($normalized, self::RESET_COMMAND . '@');
    }

    /**
     * @throws CoreException
     */
    public function resetSession(int $sessionId): void
    {
        $this->conversationMessageRepository->deleteByChatId($sessionId);
    }

    /**
     * Runs the agent over stored history and returns messages to show the user.
     * Does not persist the new turn: call {@see rememberTurn()} after the reply was delivered.
     *
     * @throws CoreException
     */
    public function reply(int $sessionId, string $text): ChatTurnResult
    {
        $modelId = $this->loadModelId();
        if ($modelId === null) {
            return $this->failure();
        }

        try {
            $answer = $this->agent->run($this->buildConversation($sessionId, $text), $modelId);
        } catch (NeuralNetworkException) {
            return $this->failure();
        }

        if (trim($answer) === '') {
            return $this->failure();
        }

        $this->logger->info('Агент вернул ответ пользователю', [
            'sessionId' => (string) $sessionId,
            'message' => $text,
            'response' => $answer,
        ]);

        return new ChatTurnResult($this->formatReply($answer), false, $answer);
    }

    /**
     * @throws PersistenceException
     */
    public function rememberTurn(int $sessionId, string $userText, string $assistantText): void
    {
        $this->unitOfWork->persist(new ConversationMessage($sessionId, 'user', $userText));
        $this->unitOfWork->persist(new ConversationMessage($sessionId, 'assistant', $assistantText));
        $this->unitOfWork->flush();
    }

    /**
     * @return list<ChatMessage>
     *
     * @throws CoreException
     */
    private function buildConversation(int $sessionId, string $text): array
    {
        $messages = [new ChatMessage('system', self::SYSTEM_PROMPT)];

        foreach ($this->conversationMessageRepository->findHistoryByChatId($sessionId) as $stored) {
            $content = $stored->getContent();
            if ($content === null || $content === '') {
                continue;
            }

            $messages[] = new ChatMessage($stored->getRole(), $content);
        }

        $messages[] = new ChatMessage('user', $text);

        return $messages;
    }

    private function formatReply(string $answer): ChatTurnMessageCollection
    {
        $parts = $this->splitter->split($answer);
        $total = count($parts);
        $messages = [];
        foreach ($parts as $index => $part) {
            $text = $total > 1
                ? sprintf("%d из %d\n\n%s", $index + 1, $total, $part)
                : $part;
            $messages[] = new ChatTurnMessage($text);
        }

        return new ChatTurnMessageCollection(...$messages);
    }

    private function failure(): ChatTurnResult
    {
        return new ChatTurnResult(
            new ChatTurnMessageCollection(new ChatTurnMessage(self::ERROR_NEURAL_NETWORK)),
            true,
        );
    }

    private function loadModelId(): ?string
    {
        try {
            $models = $this->neuralNetworkGateway->listModels();
        } catch (NeuralNetworkException) {
            return null;
        }

        if ($models->count() === 0) {
            return null;
        }

        return $models->all()[0]->id;
    }
}
