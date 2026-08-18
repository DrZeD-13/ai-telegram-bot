<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram\Mapper;

use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramUserMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncomingTelegramMessageMapper::class)]
#[CoversMethod(IncomingTelegramMessageMapper::class, 'map')]
final class IncomingTelegramMessageMapperTest extends TestCase
{
    public function testMapBuildsIncomingMessageFromTelegramPayload(): void
    {
        $mapper = new IncomingTelegramMessageMapper(new TelegramUserMapper(), new TelegramChatMapper());

        $message = $mapper->map(793810587, [
            'message_id' => 1220,
            'from' => [
                'id' => 455708771,
                'is_bot' => false,
                'first_name' => 'Павел',
                'last_name' => 'Наумов',
                'username' => 'DrZeD13',
                'language_code' => 'ru',
            ],
            'chat' => [
                'id' => 455708771,
                'first_name' => 'Павел',
                'last_name' => 'Наумов',
                'username' => 'DrZeD13',
                'type' => 'private',
            ],
            'date' => 1787069706,
            'text' => 'Тест',
        ]);

        self::assertSame(793810587, $message->updateId);
        self::assertSame(1220, $message->messageId);
        self::assertNotNull($message->from);
        self::assertSame(455708771, $message->from->id);
        self::assertFalse($message->from->isBot);
        self::assertSame('Павел', $message->from->firstName);
        self::assertSame('Наумов', $message->from->lastName);
        self::assertSame('DrZeD13', $message->from->username);
        self::assertSame('ru', $message->from->languageCode);
        self::assertSame(455708771, $message->chat->id);
        self::assertSame('private', $message->chat->type);
        self::assertSame('Павел', $message->chat->firstName);
        self::assertSame('Наумов', $message->chat->lastName);
        self::assertSame('DrZeD13', $message->chat->username);
        self::assertSame(1787069706, $message->date);
        self::assertSame('Тест', $message->text);
    }

    public function testMapAllowsMissingOptionalFromAndText(): void
    {
        $mapper = new IncomingTelegramMessageMapper(new TelegramUserMapper(), new TelegramChatMapper());

        $message = $mapper->map(1, [
            'message_id' => 3,
            'chat' => ['id' => 9, 'type' => 'private'],
            'date' => 1,
        ]);

        self::assertNull($message->from);
        self::assertNull($message->text);
    }
}
