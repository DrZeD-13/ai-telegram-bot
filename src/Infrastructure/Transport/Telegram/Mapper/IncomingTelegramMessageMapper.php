<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Dto\IncomingTelegramMessage;
use App\Application\Dto\TelegramUser;
use App\Application\Exception\TelegramBotTransportException;

final readonly class IncomingTelegramMessageMapper
{
    public function __construct(
        private TelegramUserMapper $userMapper,
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

        if (!isset($message['date']) || !is_int($message['date'])) {
            throw new TelegramBotTransportException('Telegram message payload is missing a valid date.');
        }

        if (!isset($message['chat']) || !is_array($message['chat'])) {
            throw new TelegramBotTransportException('Telegram message payload is missing a chat object.');
        }

        /** @var array<string, mixed> $chat */
        $chat = $message['chat'];

        $text = $this->optionalString($message, 'text');

        return new IncomingTelegramMessage(
            updateId: $updateId,
            messageId: $message['message_id'],
            from: $this->mapFrom($message),
            chat: $this->chatMapper->map($chat),
            date: $message['date'],
            text: $text,
        );
    }

    /**
     * @param array<string, mixed> $message
     *
     * @throws TelegramBotTransportException
     */
    private function mapFrom(array $message): ?TelegramUser
    {
        if (!array_key_exists('from', $message) || $message['from'] === null) {
            return null;
        }

        if (!is_array($message['from'])) {
            throw new TelegramBotTransportException('Telegram message payload has an invalid from object.');
        }

        /** @var array<string, mixed> $from */
        $from = $message['from'];

        return $this->userMapper->map($from);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws TelegramBotTransportException
     */
    private function optionalString(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        if (!is_string($payload[$key])) {
            throw new TelegramBotTransportException(sprintf('Telegram message payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
