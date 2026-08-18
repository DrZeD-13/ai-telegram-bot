<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram\Mapper;

use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncomingTelegramMessageMapper::class)]
#[CoversMethod(IncomingTelegramMessageMapper::class, 'map')]
final class IncomingTelegramMessageMapperTest extends TestCase
{
    public function testMapBuildsIncomingMessage(): void
    {
        $mapper = new IncomingTelegramMessageMapper(new TelegramChatMapper());

        $message = $mapper->map(15, [
            'message_id' => 3,
            'chat' => ['id' => 9],
            'text' => 'hi',
        ]);

        self::assertSame(15, $message->updateId);
        self::assertSame(9, $message->chatId);
        self::assertSame(3, $message->messageId);
        self::assertSame('hi', $message->text);
    }

    public function testMapUsesEmptyTextWhenMissing(): void
    {
        $mapper = new IncomingTelegramMessageMapper(new TelegramChatMapper());

        $message = $mapper->map(1, [
            'message_id' => 3,
            'chat' => ['id' => 9],
        ]);

        self::assertSame('', $message->text);
    }
}
