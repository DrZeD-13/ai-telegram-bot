<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Dto\TelegramChat;
use App\Application\Exception\TelegramBotTransportException;

final readonly class TelegramChatMapper
{
    /**
     * @param array<string, mixed> $chat
     *
     * @throws TelegramBotTransportException
     */
    public function map(array $chat): TelegramChat
    {
        if (!array_key_exists('id', $chat) || (!is_int($chat['id']) && !is_string($chat['id']))) {
            throw new TelegramBotTransportException('Telegram chat payload is missing a valid id.');
        }

        if (!isset($chat['type']) || !is_string($chat['type'])) {
            throw new TelegramBotTransportException('Telegram chat payload is missing a valid type.');
        }

        return new TelegramChat(
            id: $chat['id'],
            type: $chat['type'],
            title: $this->optionalString($chat, 'title'),
            username: $this->optionalString($chat, 'username'),
            firstName: $this->optionalString($chat, 'first_name'),
            lastName: $this->optionalString($chat, 'last_name'),
            isForum: $this->optionalTrue($chat, 'is_forum'),
            isDirectMessages: $this->optionalTrue($chat, 'is_direct_messages'),
        );
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
            throw new TelegramBotTransportException(sprintf('Telegram chat payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws TelegramBotTransportException
     */
    private function optionalTrue(array $payload, string $key): ?bool
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        if (!is_bool($payload[$key])) {
            throw new TelegramBotTransportException(sprintf('Telegram chat payload has a non-bool %s.', $key));
        }

        return $payload[$key];
    }
}
