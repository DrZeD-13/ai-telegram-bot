<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\ConversationSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(ConversationSession::class)]
#[CoversMethod(ConversationSession::class, '__construct')]
#[CoversMethod(ConversationSession::class, 'getId')]
#[CoversMethod(ConversationSession::class, 'getTelegramChatId')]
#[CoversMethod(ConversationSession::class, 'belongsToTelegramChat')]
#[CoversMethod(ConversationSession::class, 'markActive')]
final class ConversationSessionTest extends TestCase
{
    public function testStoresTelegramChatIdAndUuid(): void
    {
        $session = new ConversationSession(42);

        self::assertInstanceOf(UuidV7::class, $session->getId());
        self::assertSame(42, $session->getTelegramChatId());
        self::assertTrue($session->belongsToTelegramChat(42));
        self::assertFalse($session->belongsToTelegramChat(99));
        $before = $session->getLastActiveAt();
        $session->markActive();
        self::assertGreaterThanOrEqual($before->getTimestamp(), $session->getLastActiveAt()->getTimestamp());
    }

    public function testConsoleSessionHasNoTelegramChat(): void
    {
        $session = new ConversationSession();

        self::assertNull($session->getTelegramChatId());
        self::assertFalse($session->belongsToTelegramChat(42));
    }
}
