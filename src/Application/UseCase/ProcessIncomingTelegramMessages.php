<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Dto\ChatMessage;
use App\Application\Dto\IncomingTelegramMessage;
use App\Application\Exception\NeuralNetworkException;
use App\Application\Exception\PersistenceException;
use App\Application\Exception\TelegramBotConfigurationException;
use App\Application\Exception\TelegramBotException;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Logger\LoggerService;
use App\Application\Port\AiAgent;
use App\Application\Port\NeuralNetworkGateway;
use App\Application\Port\TelegramBotGateway;
use App\Application\Port\UnitOfWork;
use App\Application\Service\TelegramMessageSplitter;
use App\Domain\Entity\ConversationMessage;
use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Exception\CoreException;
use App\Domain\Exception\EmptyProcessedTelegramMessageErrorTextException;
use App\Domain\Repository\ConversationMessageRepository;
use App\Domain\Repository\ProcessedTelegramMessageRepository;
use DateTimeImmutable;

final class ProcessIncomingTelegramMessages
{
    private const int CHUNK_SIZE = 100;
    private const string RESET_COMMAND = '/new';
    private const string SYSTEM_PROMPT = 'Ты — полезный ИИ-агент в Telegram. '
        . 'У тебя есть инструмент shell для выполнения команд в оболочке хоста — используй его, когда для ответа '
        . 'нужно выполнить команду или проверить состояние системы. Отвечай пользователю на русском языке.';

    private const string PROCESSING_NOTICE = 'Запрос обрабатывается, пожалуйста подождите…';
    private const string RESET_NOTICE = 'Сессия сброшена. Можете начать новый диалог.';
    private const string ERROR_NEURAL_NETWORK = 'сервис временно не доступен по пробуйте позднее';
    private const string ERROR_DELIVERY = 'сообщение не удалось доставить';

    public function __construct(
        private readonly TelegramBotGateway $telegramBotGateway,
        private readonly NeuralNetworkGateway $neuralNetworkGateway,
        private readonly ProcessedTelegramMessageRepository $processedTelegramMessageRepository,
        private readonly ConversationMessageRepository $conversationMessageRepository,
        private readonly AiAgent $agent,
        private readonly TelegramMessageSplitter $splitter,
        private readonly UnitOfWork $unitOfWork,
        private readonly LoggerService $logger,
    ) {
    }

    /**
     * @throws CoreException
     * @throws EmptyProcessedTelegramMessageErrorTextException
     * @throws PersistenceException
     * @throws TelegramBotConfigurationException
     * @throws TelegramBotTransportException
     */
    public function execute(): void
    {
        try {
            $maxUpdateId = $this->processedTelegramMessageRepository->findMaxUpdateId();
            $offset = $maxUpdateId === null ? null : $maxUpdateId + 1;
            $incomingMessages = $this->telegramBotGateway->getMessages($offset);

            if ($incomingMessages->count() === 0) {
                return;
            }

            $modelId = null;
            $modelResolved = false;

            foreach (array_chunk($incomingMessages->all(), self::CHUNK_SIZE) as $chunk) {
                $persistedInChunk = false;

                foreach ($chunk as $incomingMessage) {
                    $text = $incomingMessage->text;
                    if ($text === null || $text === '') {
                        continue;
                    }

                    $existing = $this->processedTelegramMessageRepository->findOneByChatAndMessageId(
                        $incomingMessage->chat->id,
                        $incomingMessage->messageId,
                    );
                    if ($existing !== null) {
                        continue;
                    }

                    if ($this->isResetCommand($text)) {
                        $this->unitOfWork->persist($this->resetConversation($incomingMessage, $text));
                        $persistedInChunk = true;

                        continue;
                    }

                    if (!$modelResolved) {
                        $modelId = $this->loadModelId();
                        $modelResolved = true;
                    }

                    foreach ($this->processIncomingMessage($incomingMessage, $text, $modelId) as $entity) {
                        $this->unitOfWork->persist($entity);
                        $persistedInChunk = true;
                    }
                }

                if ($persistedInChunk) {
                    $this->unitOfWork->flush();
                }

                $this->unitOfWork->clear();
            }
        } finally {
            $this->unitOfWork->clear();
        }
    }

    /**
     * @return list<ProcessedTelegramMessage|ConversationMessage>
     *
     * @throws CoreException
     * @throws EmptyProcessedTelegramMessageErrorTextException
     */
    private function processIncomingMessage(
        IncomingTelegramMessage $incomingMessage,
        string $text,
        ?string $modelId,
    ): array {
        $this->sendProcessingNotice($incomingMessage);

        if ($modelId === null) {
            return [$this->markFailed($incomingMessage, $text, self::ERROR_NEURAL_NETWORK)];
        }

        try {
            $answer = $this->agent->run($this->buildConversation($incomingMessage->chat->id, $text), $modelId);
        } catch (NeuralNetworkException) {
            return [$this->markFailed($incomingMessage, $text, self::ERROR_NEURAL_NETWORK)];
        }

        if (trim($answer) === '') {
            return [$this->markFailed($incomingMessage, $text, self::ERROR_NEURAL_NETWORK)];
        }

        $this->logger->info('Агент вернул ответ пользователю', [
            'userId' => $this->userId($incomingMessage),
            'message' => $text,
            'response' => $answer,
        ]);

        try {
            $this->sendReply($incomingMessage->chat->id, $answer);
        } catch (TelegramBotException) {
            return [$this->markFailed($incomingMessage, $text, self::ERROR_DELIVERY)];
        }

        $processed = $this->createEntity($incomingMessage, $text);
        $processed->markProcessedSuccess();

        return [
            $processed,
            new ConversationMessage($incomingMessage->chat->id, 'user', $text),
            new ConversationMessage($incomingMessage->chat->id, 'assistant', $answer),
        ];
    }

    /**
     * Builds the message list for the agent: system prompt, stored history, current user text.
     *
     * @return list<ChatMessage>
     *
     * @throws CoreException
     */
    private function buildConversation(int $chatId, string $text): array
    {
        $messages = [new ChatMessage('system', self::SYSTEM_PROMPT)];

        foreach ($this->conversationMessageRepository->findHistoryByChatId($chatId) as $stored) {
            $content = $stored->getContent();
            if ($content === null || $content === '') {
                continue;
            }

            $messages[] = new ChatMessage($stored->getRole(), $content);
        }

        $messages[] = new ChatMessage('user', $text);

        return $messages;
    }

    /**
     * @throws CoreException
     * @throws EmptyProcessedTelegramMessageErrorTextException
     */
    private function resetConversation(IncomingTelegramMessage $incomingMessage, string $text): ProcessedTelegramMessage
    {
        $this->conversationMessageRepository->deleteByChatId($incomingMessage->chat->id);

        $entity = $this->createEntity($incomingMessage, $text);

        try {
            $this->telegramBotGateway->sendMessage($incomingMessage->chat->id, self::RESET_NOTICE);
            $entity->markProcessedSuccess();
        } catch (TelegramBotException $exception) {
            $this->logger->logException('Не удалось подтвердить сброс сессии пользователю', $exception, [
                'chatId' => (string) $incomingMessage->chat->id,
            ]);
            $entity->markProcessedError(self::ERROR_DELIVERY);
        }

        return $entity;
    }

    private function sendProcessingNotice(IncomingTelegramMessage $incomingMessage): void
    {
        try {
            $this->telegramBotGateway->sendMessage($incomingMessage->chat->id, self::PROCESSING_NOTICE);
        } catch (TelegramBotException $exception) {
            $this->logger->logException('Не удалось отправить уведомление об обработке запроса', $exception, [
                'chatId' => (string) $incomingMessage->chat->id,
            ]);
        }
    }

    /**
     * Sends the reply, splitting it into "N из M" parts when it exceeds one Telegram message.
     *
     * @throws TelegramBotException
     */
    private function sendReply(int $chatId, string $answer): void
    {
        $parts = $this->splitter->split($answer);
        $total = count($parts);

        foreach ($parts as $index => $part) {
            $message = $total > 1
                ? sprintf("%d из %d\n\n%s", $index + 1, $total, $part)
                : $part;

            $this->telegramBotGateway->sendMessage($chatId, $message);
        }
    }

    private function isResetCommand(string $text): bool
    {
        $normalized = strtolower(trim($text));

        return $normalized === self::RESET_COMMAND || str_starts_with($normalized, self::RESET_COMMAND . '@');
    }

    private function userId(IncomingTelegramMessage $incomingMessage): string
    {
        $from = $incomingMessage->from;

        return $from === null ? '' : (string) $from->id;
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

    /**
     * @throws EmptyProcessedTelegramMessageErrorTextException
     */
    private function markFailed(
        IncomingTelegramMessage $incomingMessage,
        string $text,
        string $errorText,
    ): ProcessedTelegramMessage {
        $entity = $this->createEntity($incomingMessage, $text);
        $entity->markProcessedError($errorText);

        try {
            $this->telegramBotGateway->sendMessage($incomingMessage->chat->id, $errorText);
            $this->logger->info('Пользователю отправлено сообщение об ошибке обработки', [
                'userId' => $this->userId($incomingMessage),
                'chatId' => (string) $incomingMessage->chat->id,
                'errorText' => $errorText,
            ]);
        } catch (TelegramBotException $exception) {
            $this->logger->logException(
                'Не удалось отправить пользователю сообщение об ошибке обработки',
                $exception,
                [
                    'chatId' => (string) $incomingMessage->chat->id,
                    'messageId' => (string) $incomingMessage->messageId,
                    'updateId' => (string) $incomingMessage->updateId,
                ],
            );
        }

        return $entity;
    }

    private function createEntity(IncomingTelegramMessage $incomingMessage, string $text): ProcessedTelegramMessage
    {
        $from = $incomingMessage->from;

        return new ProcessedTelegramMessage(
            chatId: (int) $incomingMessage->chat->id,
            messageId: $incomingMessage->messageId,
            updateId: $incomingMessage->updateId,
            sentAt: new DateTimeImmutable('@' . $incomingMessage->date),
            userFirstName: $from?->firstName,
            userLastName: $from?->lastName,
            userNickname: $from?->username,
            text: $text,
        );
    }
}
