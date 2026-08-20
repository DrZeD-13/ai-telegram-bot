<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Dto\IncomingTelegramMessageCollection;
use App\Application\Dto\SentTelegramMessage;
use App\Application\Exception\TelegramBotConfigurationException;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Exception\TelegramBotValidationException;

interface TelegramBotGateway
{
    /**
     * @throws TelegramBotConfigurationException
     * @throws TelegramBotTransportException
     */
    public function getMessages(?int $offset = null): IncomingTelegramMessageCollection;

    /**
     * @throws TelegramBotConfigurationException
     * @throws TelegramBotTransportException
     * @throws TelegramBotValidationException
     */
    public function sendMessage(int $chatId, string $text): SentTelegramMessage;
}
