<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Dto\IncomingTelegramMessage;
use App\Application\Exception\TelegramBotTransportException;

final readonly class IncomingTelegramMessageMapper
{
    public function __construct(
        private TelegramChatMapper $chatMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $message
     *
     * @throws TelegramBotTransportException
     */
    public function map(int $updateId, array $message): IncomingTelegramMessage
    {
        if (!isset($message['message_id']) || !is_int($message['message_id'])) {
            throw new TelegramBotTransportException('Telegram message payload is missing a valid message_id.');
        }

        if (!isset($message['chat']) || !is_array($message['chat'])) {
            throw new TelegramBotTransportException('Telegram message payload is missing a chat object.');
        }

        /** @var array<string, mixed> $chat */
        $chat = $message['chat'];

        $text = $message['text'] ?? '';
        if (!is_string($text)) {
            throw new TelegramBotTransportException('Telegram message payload has a non-string text.');
        }

        return new IncomingTelegramMessage(
            updateId: $updateId,
            chatId: $this->chatMapper->map($chat),
            messageId: $message['message_id'],
            text: $text,
        );
    }
}
