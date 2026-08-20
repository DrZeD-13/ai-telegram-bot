<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram;

use App\Application\Dto\IncomingTelegramMessageCollection;
use App\Application\Dto\SentTelegramMessage;
use App\Application\Dto\TelegramChat;
use App\Application\Port\TelegramBotGateway;
use App\Infrastructure\Transport\Telegram\TelegramBotGatewaySelector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramBotGatewaySelector::class)]
#[CoversMethod(TelegramBotGatewaySelector::class, 'getMessages')]
#[CoversMethod(TelegramBotGatewaySelector::class, 'sendMessage')]
final class TelegramBotGatewaySelectorTest extends TestCase
{
    public function testDelegatesToFixturesWhenEnabled(): void
    {
        $httpClient = $this->createMock(TelegramBotGateway::class);
        $httpClient->expects(self::never())->method('getMessages');
        $httpClient->expects(self::never())->method('sendMessage');

        $fixtureGateway = $this->createMock(TelegramBotGateway::class);
        $fixtureGateway->expects(self::once())
            ->method('getMessages')
            ->with(5)
            ->willReturn(new IncomingTelegramMessageCollection());
        $fixtureGateway->expects(self::once())
            ->method('sendMessage')
            ->with(1, 'ok')
            ->willReturn($this->sentMessage());

        $selector = new TelegramBotGatewaySelector($httpClient, $fixtureGateway, true);
        $selector->getMessages(5);
        $selector->sendMessage(1, 'ok');
    }

    public function testDelegatesToHttpClientWhenFixturesDisabled(): void
    {
        $httpClient = $this->createMock(TelegramBotGateway::class);
        $httpClient->expects(self::once())
            ->method('getMessages')
            ->with(null)
            ->willReturn(new IncomingTelegramMessageCollection());
        $httpClient->expects(self::once())
            ->method('sendMessage')
            ->with(1, 'ok')
            ->willReturn($this->sentMessage());

        $fixtureGateway = $this->createMock(TelegramBotGateway::class);
        $fixtureGateway->expects(self::never())->method('getMessages');
        $fixtureGateway->expects(self::never())->method('sendMessage');

        $selector = new TelegramBotGatewaySelector($httpClient, $fixtureGateway, false);
        $selector->getMessages();
        $selector->sendMessage(1, 'ok');
    }

    private function sentMessage(): SentTelegramMessage
    {
        return new SentTelegramMessage(
            messageId: 1,
            from: null,
            chat: new TelegramChat(1, 'private', null, null, null, null, null, null),
            date: 1,
            text: 'ok',
        );
    }
}
