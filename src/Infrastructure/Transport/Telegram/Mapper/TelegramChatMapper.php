<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Exception\TelegramBotTransportException;

final readonly class TelegramChatMapper
{
    /**
     * @param array<string, mixed> $chat
     *
     * @throws TelegramBotTransportException
     */
    public function map(array $chat): int|string
    {
        if (!array_key_exists('id', $chat) || (!is_int($chat['id']) && !is_string($chat['id']))) {
            throw new TelegramBotTransportException('Telegram chat payload is missing a valid id.');
        }

        return $chat['id'];
    }
}
