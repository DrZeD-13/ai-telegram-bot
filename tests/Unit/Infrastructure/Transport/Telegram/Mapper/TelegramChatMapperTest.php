<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram\Mapper;

use App\Application\Exception\TelegramBotTransportException;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramChatMapper::class)]
#[CoversMethod(TelegramChatMapper::class, 'map')]
final class TelegramChatMapperTest extends TestCase
{
    public function testMapBuildsChatFromTelegramPayload(): void
    {
        $chat = (new TelegramChatMapper())->map([
            'id' => 455708771,
            'first_name' => 'Павел',
            'last_name' => 'Наумов',
            'username' => 'DrZeD13',
            'type' => 'private',
        ]);

        self::assertSame(455708771, $chat->id);
        self::assertSame('private', $chat->type);
        self::assertNull($chat->title);
        self::assertSame('Павел', $chat->firstName);
        self::assertSame('Наумов', $chat->lastName);
        self::assertSame('DrZeD13', $chat->username);
        self::assertNull($chat->isForum);
        self::assertNull($chat->isDirectMessages);
    }

    public function testMapReturnsIntegerChatIdFromNumericString(): void
    {
        $chat = (new TelegramChatMapper())->map([
            'id' => '-100123',
            'type' => 'supergroup',
        ]);

        self::assertSame(-100123, $chat->id);
        self::assertNull($chat->title);
        self::assertNull($chat->firstName);
        self::assertNull($chat->lastName);
        self::assertNull($chat->username);
    }

    public function testMapThrowsWhenIdIsMissing(): void
    {
        $this->expectException(TelegramBotTransportException::class);

        (new TelegramChatMapper())->map(['type' => 'private']);
    }
}
