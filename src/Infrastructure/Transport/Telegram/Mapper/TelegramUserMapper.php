<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Dto\TelegramUser;
use App\Application\Exception\TelegramBotTransportException;

final readonly class TelegramUserMapper
{
    /**
     * @param array<string, mixed> $user
     *
     * @throws TelegramBotTransportException
     */
    public function map(array $user): TelegramUser
    {
        if (!isset($user['id']) || !is_int($user['id'])) {
            throw new TelegramBotTransportException('Telegram user payload is missing a valid id.');
        }

        if (!isset($user['is_bot']) || !is_bool($user['is_bot'])) {
            throw new TelegramBotTransportException('Telegram user payload is missing a valid is_bot.');
        }

        if (!isset($user['first_name']) || !is_string($user['first_name'])) {
            throw new TelegramBotTransportException('Telegram user payload is missing a valid first_name.');
        }

        return new TelegramUser(
            id: $user['id'],
            isBot: $user['is_bot'],
            firstName: $user['first_name'],
            lastName: $this->optionalString($user, 'last_name'),
            username: $this->optionalString($user, 'username'),
            languageCode: $this->optionalString($user, 'language_code'),
            isPremium: $this->optionalTrue($user, 'is_premium'),
            addedToAttachmentMenu: $this->optionalTrue($user, 'added_to_attachment_menu'),
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
            throw new TelegramBotTransportException(sprintf('Telegram user payload has a non-string %s.', $key));
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
            throw new TelegramBotTransportException(sprintf('Telegram user payload has a non-bool %s.', $key));
        }

        return $payload[$key];
    }
}
