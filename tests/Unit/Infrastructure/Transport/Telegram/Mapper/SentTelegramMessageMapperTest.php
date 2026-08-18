<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram\Mapper;

use App\Infrastructure\Transport\Telegram\Mapper\SentTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(SentTelegramMessageMapper::class)]
#[CoversMethod(SentTelegramMessageMapper::class, 'map')]
final class SentTelegramMessageMapperTest extends TestCase
{
    public function testMapBuildsSentMessage(): void
    {
        $mapper = new SentTelegramMessageMapper(new TelegramChatMapper());

        $sent = $mapper->map([
            'message_id' => 77,
            'chat' => ['id' => 55],
            'text' => 'sent',
        ]);

        self::assertSame(55, $sent->chatId);
        self::assertSame(77, $sent->messageId);
        self::assertSame('sent', $sent->text);
    }
}
