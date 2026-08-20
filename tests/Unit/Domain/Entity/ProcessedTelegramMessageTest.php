<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\ProcessedTelegramMessage;
use App\Domain\Entity\ProcessedTelegramMessageStatus;
use App\Domain\Exception\EmptyProcessedTelegramMessageErrorTextException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(ProcessedTelegramMessage::class)]
#[CoversMethod(ProcessedTelegramMessage::class, '__construct')]
#[CoversMethod(ProcessedTelegramMessage::class, 'markProcessedSuccess')]
#[CoversMethod(ProcessedTelegramMessage::class, 'markProcessedError')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getId')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getChatId')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getMessageId')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getUpdateId')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getSentAt')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getUserFirstName')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getUserLastName')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getUserNickname')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getText')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getStatus')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getErrorText')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getCreatedAt')]
#[CoversMethod(ProcessedTelegramMessage::class, 'getUpdatedAt')]
#[CoversClass(ProcessedTelegramMessageStatus::class)]
final class ProcessedTelegramMessageTest extends TestCase
{
    public function testNewRecordIsPendingWithEmptyError(): void
    {
        $sentAt = new DateTimeImmutable('2026-08-20 09:00:00');
        $message = new ProcessedTelegramMessage(
            chatId: -1001234567890,
            messageId: 42,
            updateId: 1001,
            sentAt: $sentAt,
            userFirstName: 'Павел',
            userLastName: 'Наумов',
            userNickname: 'DrZeD13',
            text: 'Привет',
        );

        self::assertInstanceOf(UuidV7::class, $message->getId());
        self::assertSame(-1001234567890, $message->getChatId());
        self::assertSame(42, $message->getMessageId());
        self::assertSame(1001, $message->getUpdateId());
        self::assertSame($sentAt, $message->getSentAt());
        self::assertSame('Павел', $message->getUserFirstName());
        self::assertSame('Наумов', $message->getUserLastName());
        self::assertSame('DrZeD13', $message->getUserNickname());
        self::assertSame('Привет', $message->getText());
        self::assertSame(ProcessedTelegramMessageStatus::Pending, $message->getStatus());
        self::assertNull($message->getErrorText());
        self::assertInstanceOf(DateTimeImmutable::class, $message->getCreatedAt());
        self::assertInstanceOf(DateTimeImmutable::class, $message->getUpdatedAt());
    }

    public function testMissingOptionalFieldsAreStoredEmpty(): void
    {
        $message = new ProcessedTelegramMessage(
            chatId: 1,
            messageId: 2,
            updateId: 3,
            sentAt: new DateTimeImmutable(),
        );

        self::assertSame(3, $message->getUpdateId());
        self::assertNull($message->getUserFirstName());
        self::assertNull($message->getUserLastName());
        self::assertNull($message->getUserNickname());
        self::assertNull($message->getText());
        self::assertSame(ProcessedTelegramMessageStatus::Pending, $message->getStatus());
    }

    public function testMarkProcessedSuccessClearsError(): void
    {
        $message = new ProcessedTelegramMessage(
            chatId: 1,
            messageId: 2,
            updateId: 3,
            sentAt: new DateTimeImmutable(),
        );
        $message->markProcessedError('сбой');

        $message->markProcessedSuccess();

        self::assertSame(ProcessedTelegramMessageStatus::ProcessedSuccess, $message->getStatus());
        self::assertNull($message->getErrorText());
    }

    public function testMarkProcessedErrorStoresText(): void
    {
        $message = new ProcessedTelegramMessage(
            chatId: 1,
            messageId: 2,
            updateId: 3,
            sentAt: new DateTimeImmutable(),
        );

        $message->markProcessedError('нейросеть недоступна');

        self::assertSame(ProcessedTelegramMessageStatus::ProcessedError, $message->getStatus());
        self::assertSame('нейросеть недоступна', $message->getErrorText());
    }

    public function testMarkProcessedErrorRejectsEmptyText(): void
    {
        $message = new ProcessedTelegramMessage(
            chatId: 1,
            messageId: 2,
            updateId: 3,
            sentAt: new DateTimeImmutable(),
        );

        $this->expectException(EmptyProcessedTelegramMessageErrorTextException::class);

        $message->markProcessedError('');
    }
}
