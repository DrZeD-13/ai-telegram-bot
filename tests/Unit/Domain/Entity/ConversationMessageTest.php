<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\ConversationMessage;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(ConversationMessage::class)]
#[CoversMethod(ConversationMessage::class, '__construct')]
#[CoversMethod(ConversationMessage::class, 'getId')]
#[CoversMethod(ConversationMessage::class, 'getChatId')]
#[CoversMethod(ConversationMessage::class, 'getRole')]
#[CoversMethod(ConversationMessage::class, 'getContent')]
#[CoversMethod(ConversationMessage::class, 'getCreatedAt')]
final class ConversationMessageTest extends TestCase
{
    public function testStoresChatRoleAndContent(): void
    {
        $message = new ConversationMessage(88, 'user', 'вопрос');

        self::assertInstanceOf(UuidV7::class, $message->getId());
        self::assertSame(88, $message->getChatId());
        self::assertSame('user', $message->getRole());
        self::assertSame('вопрос', $message->getContent());
        self::assertInstanceOf(DateTimeImmutable::class, $message->getCreatedAt());
    }
}
