<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Dto\SentTelegramMessage;
use App\Application\Dto\TelegramUser;
use App\Application\Exception\TelegramBotTransportException;

final readonly class SentTelegramMessageMapper
{
    public function __construct(
        private TelegramUserMapper $userMapper,
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

        if (!isset($result['date']) || !is_int($result['date'])) {
            throw new TelegramBotTransportException('Telegram sendMessage result is missing a valid date.');
        }

        if (!isset($result['chat']) || !is_array($result['chat'])) {
            throw new TelegramBotTransportException('Telegram sendMessage result is missing a chat object.');
        }

        /** @var array<string, mixed> $chat */
        $chat = $result['chat'];

        $text = $this->optionalString($result, 'text');

        return new SentTelegramMessage(
            messageId: $result['message_id'],
            from: $this->mapFrom($result),
            chat: $this->chatMapper->map($chat),
            date: $result['date'],
            text: $text,
        );
    }

    /**
     * @param array<string, mixed> $result
     *
     * @throws TelegramBotTransportException
     */
    private function mapFrom(array $result): ?TelegramUser
    {
        if (!array_key_exists('from', $result) || $result['from'] === null) {
            return null;
        }

        if (!is_array($result['from'])) {
            throw new TelegramBotTransportException('Telegram sendMessage result has an invalid from object.');
        }

        /** @var array<string, mixed> $from */
        $from = $result['from'];

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
            throw new TelegramBotTransportException(sprintf('Telegram sendMessage result has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
