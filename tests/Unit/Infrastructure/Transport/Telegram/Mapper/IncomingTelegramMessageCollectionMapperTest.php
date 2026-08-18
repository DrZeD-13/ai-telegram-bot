<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram\Mapper;

use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageCollectionMapper;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramMessageMapper;
use App\Infrastructure\Transport\Telegram\Mapper\IncomingTelegramUpdateMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramChatMapper;
use App\Infrastructure\Transport\Telegram\Mapper\TelegramUserMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncomingTelegramMessageCollectionMapper::class)]
#[CoversMethod(IncomingTelegramMessageCollectionMapper::class, 'map')]
final class IncomingTelegramMessageCollectionMapperTest extends TestCase
{
    public function testMapSkipsUpdatesWithoutMessage(): void
    {
        $mapper = new IncomingTelegramMessageCollectionMapper(
            new IncomingTelegramUpdateMapper(
                new IncomingTelegramMessageMapper(new TelegramUserMapper(), new TelegramChatMapper()),
            ),
        );

        $collection = $mapper->map([
            ['update_id' => 1, 'callback_query' => ['id' => 'x']],
            [
                'update_id' => 2,
                'message' => [
                    'message_id' => 4,
                    'chat' => ['id' => 8, 'type' => 'private'],
                    'date' => 1,
                    'text' => 'ok',
                ],
            ],
        ]);

        self::assertCount(1, $collection);
        self::assertSame(2, $collection->all()[0]->updateId);
    }

    public function testMapReturnsEmptyCollection(): void
    {
        $mapper = new IncomingTelegramMessageCollectionMapper(
            new IncomingTelegramUpdateMapper(
                new IncomingTelegramMessageMapper(new TelegramUserMapper(), new TelegramChatMapper()),
            ),
        );

        self::assertCount(0, $mapper->map([]));
    }
}
