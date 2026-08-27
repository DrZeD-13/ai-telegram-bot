<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Dto\IncomingTelegramMessage;
use App\Application\Exception\PersistenceException;
use App\Application\Exception\TelegramBotConfigurationException;
use App\Application\Exception\TelegramBotException;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Logger\LoggerService;
use App\Application\Port\ChatTurnHandler;
use App\Application\Port\TelegramBotGateway;
use App\Application\Port\UnitOfWork;
use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Exception\CoreException;
use App\Domain\Exception\EmptyProcessedTelegramMessageErrorTextException;
use App\Domain\Repository\ProcessedTelegramMessageRepository;
use DateTimeImmutable;

final class ProcessIncomingTelegramMessages
{
    private const int CHUNK_SIZE = 100;
    private const string ERROR_DELIVERY = 'сообщение не удалось доставить';

    public function __construct(
        private readonly TelegramBotGateway $telegramBotGateway,
        private readonly ProcessedTelegramMessageRepository $processedTelegramMessageRepository,
        private readonly ChatTurnHandler $handleChatTurn,
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

                    $this->unitOfWork->persist($this->processIncomingMessage($incomingMessage, $text));
                    $persistedInChunk = true;
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
     * @throws CoreException
     * @throws EmptyProcessedTelegramMessageErrorTextException
     * @throws PersistenceException
     */
    private function processIncomingMessage(
        IncomingTelegramMessage $incomingMessage,
        string $text,
    ): ProcessedTelegramMessage {
        $chatId = (int) $incomingMessage->chat->id;

        if ($this->handleChatTurn->isResetCommand($text)) {
            $this->handleChatTurn->resetSession($chatId);
            $entity = $this->createEntity($incomingMessage, $text);

            try {
                $this->telegramBotGateway->sendMessage($chatId, ChatTurnHandler::RESET_NOTICE);
                $entity->markProcessedSuccess();
            } catch (TelegramBotException $exception) {
                $this->logger->logException('Не удалось подтвердить сброс сессии пользователю', $exception, [
                    'chatId' => (string) $chatId,
                ]);
                $entity->markProcessedError(self::ERROR_DELIVERY);
            }

            return $entity;
        }

        $this->sendProcessingNotice($incomingMessage);

        $result = $this->handleChatTurn->reply($chatId, $text);
        if ($result->failed || $result->assistantText === null) {
            return $this->markFailed($incomingMessage, $text, ChatTurnHandler::ERROR_NEURAL_NETWORK);
        }

        try {
            foreach ($result->messages as $message) {
                $this->telegramBotGateway->sendMessage($chatId, $message->text);
            }
        } catch (TelegramBotException) {
            return $this->markFailed($incomingMessage, $text, self::ERROR_DELIVERY);
        }

        $this->handleChatTurn->rememberTurn($chatId, $text, $result->assistantText);

        $processed = $this->createEntity($incomingMessage, $text);
        $processed->markProcessedSuccess();

        return $processed;
    }

    private function sendProcessingNotice(IncomingTelegramMessage $incomingMessage): void
    {
        try {
            $this->telegramBotGateway->sendMessage($incomingMessage->chat->id, ChatTurnHandler::PROCESSING_NOTICE);
        } catch (TelegramBotException $exception) {
            $this->logger->logException('Не удалось отправить уведомление об обработке запроса', $exception, [
                'chatId' => (string) $incomingMessage->chat->id,
            ]);
        }
    }

    private function userId(IncomingTelegramMessage $incomingMessage): string
    {
        $from = $incomingMessage->from;

        return $from === null ? '' : (string) $from->id;
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
