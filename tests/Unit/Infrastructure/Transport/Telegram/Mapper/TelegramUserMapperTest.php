<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\Telegram\Mapper;

use App\Infrastructure\Transport\Telegram\Mapper\TelegramUserMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramUserMapper::class)]
#[CoversMethod(TelegramUserMapper::class, 'map')]
final class TelegramUserMapperTest extends TestCase
{
    public function testMapBuildsUserFromTelegramPayload(): void
    {
        $user = (new TelegramUserMapper())->map([
            'id' => 455708771,
            'is_bot' => false,
            'first_name' => 'Павел',
            'last_name' => 'Наумов',
            'username' => 'DrZeD13',
            'language_code' => 'ru',
        ]);

        self::assertSame(455708771, $user->id);
        self::assertFalse($user->isBot);
        self::assertSame('Павел', $user->firstName);
        self::assertSame('Наумов', $user->lastName);
        self::assertSame('DrZeD13', $user->username);
        self::assertSame('ru', $user->languageCode);
        self::assertNull($user->isPremium);
        self::assertNull($user->addedToAttachmentMenu);
    }

    public function testMapAllowsMissingOptionalFields(): void
    {
        $user = (new TelegramUserMapper())->map([
            'id' => 1,
            'is_bot' => true,
            'first_name' => 'Bot',
        ]);

        self::assertNull($user->lastName);
        self::assertNull($user->username);
        self::assertNull($user->languageCode);
        self::assertNull($user->isPremium);
        self::assertNull($user->addedToAttachmentMenu);
    }
}
