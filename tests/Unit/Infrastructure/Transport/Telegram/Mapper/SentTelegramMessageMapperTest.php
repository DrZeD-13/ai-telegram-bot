<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram\Mapper;

use App\Infrastructure\Transport\Telegram\Mapper\SentTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramUserMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(SentTelegramMessageMapper::class)]
#[CoversMethod(SentTelegramMessageMapper::class, 'map')]
final class SentTelegramMessageMapperTest extends TestCase
{
    public function testMapBuildsSentMessageFromTelegramPayload(): void
    {
        $mapper = new SentTelegramMessageMapper(new TelegramUserMapper(), new TelegramChatMapper());

        $sent = $mapper->map([
            'message_id' => 77,
            'from' => [
                'id' => 1,
                'is_bot' => true,
                'first_name' => 'TestBot',
                'username' => 'test_bot',
            ],
            'chat' => [
                'id' => 55,
                'type' => 'private',
                'first_name' => 'Павел',
            ],
            'date' => 1787069706,
            'text' => 'sent',
        ]);

        self::assertSame(77, $sent->messageId);
        self::assertNotNull($sent->from);
        self::assertTrue($sent->from->isBot);
        self::assertSame(55, $sent->chat->id);
        self::assertSame('private', $sent->chat->type);
        self::assertSame(1787069706, $sent->date);
        self::assertSame('sent', $sent->text);
    }
}
