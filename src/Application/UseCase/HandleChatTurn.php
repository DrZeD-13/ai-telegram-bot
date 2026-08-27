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
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

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
        $token = $this->slashCommandToken($text);

        return $token === self::RESET_COMMAND || str_starts_with($token, self::RESET_COMMAND . '@');
    }

    public function isResumeCommand(string $text): bool
    {
        $line = strtolower($this->commandLine($text));

        return $line === '/open'
            || str_starts_with($line, '/open ')
            || str_starts_with($line, '/open@');
    }

    public function parseResumeSessionId(string $text): ?Uuid
    {
        if (!preg_match('/^\/open(?:@[^\s]+)?\s+(\S+)/i', $this->commandLine($text), $matches)) {
            return null;
        }

        $rawId = $matches[1];
        if (!Uuid::isValid($rawId)) {
            return null;
        }

        return Uuid::fromString($rawId);
    }

    public function newSessionId(): Uuid
    {
        return new UuidV7();
    }

    public function newSessionNotice(Uuid $previousSessionId, Uuid $currentSessionId): string
    {
        $previous = $previousSessionId->toRfc4122();
        $current = $currentSessionId->toRfc4122();

        return "Новая сессия начата. История предыдущей сохранена.\n\n"
            . "Предыдущая сессия:\n{$previous}\n\n"
            . "Текущая сессия:\n{$current}\n\n"
            . "Вернуться к предыдущей: /open {$previous}";
    }

    public function resumeSessionNotice(Uuid $currentSessionId): string
    {
        $current = $currentSessionId->toRfc4122();

        return "Сессия восстановлена.\n\nТекущая сессия:\n{$current}";
    }

    private function commandLine(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, "\u{FEFF}")) {
            $text = substr($text, strlen("\u{FEFF}"));
            $text = trim($text);
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return $text;
    }

    /**
     * Slash-commands are ASCII. Invalid bytes and non-ASCII junk around `/new`
     * must not prevent a session reset (console encoding glitches).
     */
    private function slashCommandToken(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, "\u{FEFF}")) {
            $text = substr($text, strlen("\u{FEFF}"));
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = strtolower($text);
        if ($text === '' || !str_starts_with($text, '/')) {
            return $text;
        }

        $ascii = preg_replace('/[^a-z0-9\/@_]/', '', $text);

        return is_string($ascii) ? $ascii : $text;
    }

    /**
     * @throws CoreException
     */
    public function reply(Uuid $sessionId, string $text): ChatTurnResult
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
            'sessionId' => $sessionId->toRfc4122(),
            'message' => $text,
            'response' => $answer,
        ]);

        return new ChatTurnResult($this->formatReply($answer), false, $answer);
    }

    /**
     * @throws PersistenceException
     */
    public function rememberTurn(Uuid $sessionId, string $userText, string $assistantText): void
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
    private function buildConversation(Uuid $sessionId, string $text): array
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
