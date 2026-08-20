<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\Telegram;

use App\Application\Dto\IncomingTelegramMessageCollection;
use App\Application\Dto\SentTelegramMessage;
use App\Application\Exception\TelegramBotConfigurationException;
use App\Application\Exception\TelegramBotTransportException;
use App\Application\Exception\TelegramBotValidationException;
use App\Application\Port\TelegramBotGateway;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(TelegramBotGateway::class)]
final readonly class TelegramBotGatewaySelector implements TelegramBotGateway
{
    public function __construct(
        #[Autowire(service: TelegramBotHttpClient::class)]
        private TelegramBotGateway $httpClient,
        #[Autowire(service: TelegramBotFixtureGateway::class)]
        private TelegramBotGateway $fixtureGateway,
        #[Autowire('%env(bool:TELEGRAM_USE_FIXTURES)%')]
        private bool $useFixtures,
    ) {
    }

    /**
     * @throws TelegramBotConfigurationException
     * @throws TelegramBotTransportException
     */
    public function getMessages(?int $offset = null): IncomingTelegramMessageCollection
    {
        return $this->delegate()->getMessages($offset);
    }

    /**
     * @throws TelegramBotConfigurationException
     * @throws TelegramBotTransportException
     * @throws TelegramBotValidationException
     */
    public function sendMessage(int $chatId, string $text): SentTelegramMessage
    {
        return $this->delegate()->sendMessage($chatId, $text);
    }

    private function delegate(): TelegramBotGateway
    {
        if ($this->useFixtures) {
            return $this->fixtureGateway;
        }

        return $this->httpClient;
    }
}
