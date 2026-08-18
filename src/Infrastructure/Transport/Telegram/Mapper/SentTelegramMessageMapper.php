<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Dto\SentTelegramMessage;
use App\Application\Exception\TelegramBotTransportException;

final readonly class SentTelegramMessageMapper
{
    public function __construct(
        private TelegramChatMapper $chatMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $result
     *
     * @throws TelegramBotTransportException
     */
    public function map(array $result): SentTelegramMessage
    {
        if (!isset($result['message_id']) || !is_int($result['message_id'])) {
            throw new TelegramBotTransportException('Telegram sendMessage result is missing a valid message_id.');
        }

        if (!isset($result['chat']) || !is_array($result['chat'])) {
            throw new TelegramBotTransportException('Telegram sendMessage result is missing a chat object.');
        }

        /** @var array<string, mixed> $chat */
        $chat = $result['chat'];

        $text = $result['text'] ?? '';
        if (!is_string($text)) {
            throw new TelegramBotTransportException('Telegram sendMessage result has a non-string text.');
        }

        return new SentTelegramMessage(
            chatId: $this->chatMapper->map($chat),
            messageId: $result['message_id'],
            text: $text,
        );
    }
}
