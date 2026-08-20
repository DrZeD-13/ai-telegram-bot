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
    private const string ERROR_VALIDATION = 'запрос слишком длинный сделайте не более 1024 символов';
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

        /** @var array<string, true> $seenChatMessageIds */
        $seenChatMessageIds = [];
        $modelId = false;

        foreach (array_chunk($incomingMessages->all(), self::CHUNK_SIZE) as $chunk) {
            $persistedInChunk = false;

            foreach ($chunk as $incomingMessage) {
                $text = $incomingMessage->text;
                if ($text === null || $text === '') {
                    continue;
                }

                $chatMessageKey = $this->chatMessageKey($incomingMessage);
                if (isset($seenChatMessageIds[$chatMessageKey])) {
                    continue;
                }

                $existing = $this->processedTelegramMessageRepository->findOneByChatAndMessageId(
                    (int) $incomingMessage->chat->id,
                    $incomingMessage->messageId,
                );
                if ($existing !== null) {
                    $seenChatMessageIds[$chatMessageKey] = true;
                    continue;
                }

                $seenChatMessageIds[$chatMessageKey] = true;

                if (mb_strlen($text) <= self::MAX_USER_TEXT_LENGTH && $modelId === false) {
                    $modelId = $this->loadModelId();
                }

                $entity = $this->processIncomingMessage(
                    $incomingMessage,
                    $text,
                    $modelId === false ? null : $modelId,
                );
                $this->unitOfWork->persist($entity);
                $persistedInChunk = true;
            }

            if ($persistedInChunk) {
                $this->unitOfWork->flush();
            }
        }
    }

    /**
     * @throws EmptyProcessedTelegramMessageErrorTextException
     */
    private function processIncomingMessage(
        IncomingTelegramMessage $incomingMessage,
        string $text,
        ?string $modelId,
    ): ProcessedTelegramMessage {
        if (mb_strlen($text) > self::MAX_USER_TEXT_LENGTH) {
            return $this->markFailed($incomingMessage, $text, self::ERROR_VALIDATION);
        }

        if ($modelId === null) {
            return $this->markFailed($incomingMessage, $text, self::ERROR_NEURAL_NETWORK);
        }

        try {
            $completion = $this->neuralNetworkGateway->createChatCompletion(new ChatCompletionRequest(
                model: $modelId,
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

        $this->logger->info('Нейросеть вернула ответ', [
            'userId' => $this->userId($incomingMessage),
            'message' => $text,
            'response' => $completion->text,
        ]);

        try {
            $this->telegramBotGateway->sendMessage($incomingMessage->chat->id, $completion->text);
        } catch (TelegramBotException) {
            return $this->markFailed($incomingMessage, $text, self::ERROR_DELIVERY);
        }

        $entity = $this->createEntity($incomingMessage, $text);
        $entity->markProcessedSuccess();

        return $entity;
    }

    private function chatMessageKey(IncomingTelegramMessage $incomingMessage): string
    {
        return (int) $incomingMessage->chat->id . ':' . $incomingMessage->messageId;
    }

    private function userId(IncomingTelegramMessage $incomingMessage): string
    {
        return (string) ($incomingMessage->from?->id ?? '');
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
        $entity = $this->createEntity(
            $incomingMessage,
            mb_substr($text, 0, self::MAX_USER_TEXT_LENGTH),
        );
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
