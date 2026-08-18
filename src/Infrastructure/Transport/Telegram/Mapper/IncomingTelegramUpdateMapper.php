<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Dto\IncomingTelegramMessage;
use App\Application\Exception\TelegramBotTransportException;

final readonly class IncomingTelegramUpdateMapper
{
    public function __construct(
        private IncomingTelegramMessageMapper $messageMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws TelegramBotTransportException
     */
    public function map(array $update): IncomingTelegramMessage
    {
        if (!isset($update['update_id']) || !is_int($update['update_id'])) {
            throw new TelegramBotTransportException('Telegram update payload is missing a valid update_id.');
        }

        if (!isset($update['message']) || !is_array($update['message'])) {
            throw new TelegramBotTransportException('Telegram update payload is missing a message object.');
        }

        /** @var array<string, mixed> $message */
        $message = $update['message'];

        return $this->messageMapper->map($update['update_id'], $message);
    }
}
