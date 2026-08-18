<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram\Mapper;

use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramUpdateMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncomingTelegramUpdateMapper::class)]
#[CoversMethod(IncomingTelegramUpdateMapper::class, 'map')]
final class IncomingTelegramUpdateMapperTest extends TestCase
{
    public function testMapDelegatesToMessageMapper(): void
    {
        $mapper = new IncomingTelegramUpdateMapper(
            new IncomingTelegramMessageMapper(new TelegramChatMapper()),
        );

        $message = $mapper->map([
            'update_id' => 88,
            'message' => [
                'message_id' => 5,
                'chat' => ['id' => 12],
                'text' => 'update',
            ],
        ]);

        self::assertSame(88, $message->updateId);
        self::assertSame(12, $message->chatId);
        self::assertSame(5, $message->messageId);
        self::assertSame('update', $message->text);
    }
}
