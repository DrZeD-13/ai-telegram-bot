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
    public function testMapReturnsIntegerChatId(): void
    {
        $chatId = (new TelegramChatMapper())->map(['id' => 42]);

        self::assertSame(42, $chatId);
    }

    public function testMapReturnsStringChatId(): void
    {
        $chatId = (new TelegramChatMapper())->map(['id' => '-100123']);

        self::assertSame('-100123', $chatId);
    }

    public function testMapThrowsWhenIdIsMissing(): void
    {
        $this->expectException(TelegramBotTransportException::class);

        (new TelegramChatMapper())->map([]);
    }
}
