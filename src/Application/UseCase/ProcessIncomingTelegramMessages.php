<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Dto\ChatCompletionRequest;
use App\Application\Dto\ChatMessage;
use App\Application\Dto\ChatMessageCollection;
use App\Application\Dto\IncomingTelegramMessage;
use App\Application\Exception\NeuralNetworkException;
use App\Application\Exception\PersistenceException;
use App\Application\Exception\TelegramBotConfigurationException;
use App\Application\Exception\TelegramBotException;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Logger\LoggerService;
use App\Application\Port\NeuralNetworkGateway;
use App\Application\Port\TelegramBotGateway;
use App\Application\Port\UnitOfWork;
use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Exception\CoreException;
use App\Domain\Exception\EmptyProcessedTelegramMessageErrorTextException;
use App\Domain\Repository\ProcessedTelegramMessageRepository;
use DateTimeImmutable;

final class ProcessIncomingTelegramMessages
{
    private const int MAX_USER_TEXT_LENGTH = 1024;
    private const int CHUNK_SIZE = 100;
    private const string AI_LENGTH_INSTRUCTION_SUFFIX = "\nответ сделай не больше 1024 символа";
    private const string ERROR_VALIDATION = 'запрос слишком длиный сделайте не более 1024 символов';
    private const string ERROR_NEURAL_NETWORK = 'сервис временно не доступен по пробуйте позднее';
    private const string ERROR_DELIVERY = 'сообщение не удалось доставить';

    public function __construct(
        private readonly TelegramBotGateway $telegramBotGateway,
        private readonly NeuralNetworkGateway $neuralNetworkGateway,
        private readonly ProcessedTelegramMessageRepository $processedTelegramMessageRepository,
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
        $maxUpdateId = $this->processedTelegramMessageRepository->findMaxUpdateId();
        $offset = $maxUpdateId === null ? null : $maxUpdateId + 1;
        $incomingMessages = $this->telegramBotGateway->getMessages($offset);

        if ($incomingMessages->count() === 0) {
            return;
        }

        $modelId = false;
        $seenChatMessageIds = [];

        foreach (array_chunk($incomingMessages->all(), self::CHUNK_SIZE) as $chunk) {
            $persistedInChunk = false;

            foreach ($chunk as $incomingMessage) {
                $entity = $this->processIncomingMessage($incomingMessage, $seenChatMessageIds, $modelId);
                if ($entity === null) {
                    continue;
                }

                $this->unitOfWork->persist($entity);
                $persistedInChunk = true;
            }

            if ($persistedInChunk) {
                $this->unitOfWork->flush();
            }
        }
    }

    /**
     * @param array<string, true> $seenChatMessageIds
     *
     * @param-out array<string, true> $seenChatMessageIds
     * @param-out string|false|null $modelId
     *
     * @throws CoreException
     * @throws EmptyProcessedTelegramMessageErrorTextException
     */
    private function processIncomingMessage(
        IncomingTelegramMessage $incomingMessage,
        array &$seenChatMessageIds,
        string|false|null &$modelId,
    ): ?ProcessedTelegramMessage {
        $text = $incomingMessage->text;
        if ($text === null || $text === '') {
            return null;
        }

        $chatId = (int) $incomingMessage->chat->id;
        $seenKey = $chatId . ':' . $incomingMessage->messageId;
        if (isset($seenChatMessageIds[$seenKey])) {
            return null;
        }

        $existing = $this->processedTelegramMessageRepository->findOneByChatAndMessageId(
            $chatId,
            $incomingMessage->messageId,
        );
        if ($existing !== null) {
            $seenChatMessageIds[$seenKey] = true;

            return null;
        }

        $seenChatMessageIds[$seenKey] = true;

        if (mb_strlen($text) > self::MAX_USER_TEXT_LENGTH) {
            return $this->markFailed($incomingMessage, $text, self::ERROR_VALIDATION);
        }

        $resolvedModelId = $this->resolveModelId($modelId);
        if ($resolvedModelId === null) {
            return $this->markFailed($incomingMessage, $text, self::ERROR_NEURAL_NETWORK);
        }

        try {
            $completion = $this->neuralNetworkGateway->createChatCompletion(new ChatCompletionRequest(
                model: $resolvedModelId,
                messages: new ChatMessageCollection(
                    new ChatMessage('user', $text . self::AI_LENGTH_INSTRUCTION_SUFFIX),
                ),
            ));
        } catch (NeuralNetworkException) {
            return $this->markFailed($incomingMessage, $text, self::ERROR_NEURAL_NETWORK);
        }

        if ($completion->text === null || $completion->text === '') {
            return $this->markFailed($incomingMessage, $text, self::ERROR_NEURAL_NETWORK);
        }

        try {
            $this->telegramBotGateway->sendMessage($incomingMessage->chat->id, $completion->text);
        } catch (TelegramBotException) {
            return $this->markFailed($incomingMessage, $text, self::ERROR_DELIVERY);
        }

        $entity = $this->createEntity($incomingMessage, $text);
        $entity->markProcessedSuccess();

        return $entity;
    }

    /**
     * @param-out string|null $modelId
     */
    private function resolveModelId(string|false|null &$modelId): ?string
    {
        if ($modelId !== false) {
            return $modelId;
        }

        try {
            $models = $this->neuralNetworkGateway->listModels();
        } catch (NeuralNetworkException) {
            $modelId = null;

            return null;
        }

        if ($models->count() === 0) {
            $modelId = null;

            return null;
        }

        $modelId = $models->all()[0]->id;

        return $modelId;
    }

    /**
     * @throws EmptyProcessedTelegramMessageErrorTextException
     */
    private function markFailed(
        IncomingTelegramMessage $incomingMessage,
        string $text,
        string $errorText,
    ): ProcessedTelegramMessage {
        $entity = $this->createEntity(
            $incomingMessage,
            mb_substr($text, 0, self::MAX_USER_TEXT_LENGTH),
        );
        $entity->markProcessedError($errorText);

        try {
            $this->telegramBotGateway->sendMessage($incomingMessage->chat->id, $errorText);
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
