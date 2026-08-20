<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Exception\EmptyProcessedTelegramMessageErrorTextException;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
#[ORM\Table(
    name: 'processed_telegram_message',
    options: ['comment' => 'Входящие сообщения Telegram и статус их обработки'],
)]
#[ORM\UniqueConstraint(
    name: 'uniq_processed_telegram_message_chat_message',
    fields: ['chatId', 'messageId'],
)]
#[ORM\HasLifecycleCallbacks]
class ProcessedTelegramMessage
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private UuidV7 $id;

    #[ORM\Column(length: 255, nullable: true, options: ['comment' => 'Имя отправителя в Telegram'])]
    private ?string $userFirstName;

    #[ORM\Column(length: 255, nullable: true, options: ['comment' => 'Фамилия отправителя в Telegram'])]
    private ?string $userLastName;

    #[ORM\Column(length: 64, nullable: true, options: ['comment' => 'Username отправителя в Telegram'])]
    private ?string $userNickname;

    #[ORM\Column(type: Types::BIGINT, options: ['comment' => 'Идентификатор чата Telegram'])]
    private int $chatId;

    #[ORM\Column(options: ['comment' => 'Идентификатор сообщения в чате Telegram'])]
    private int $messageId;

    #[ORM\Column(options: ['comment' => 'Идентификатор Telegram update'])]
    private int $updateId;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => 'Текст входящего сообщения'])]
    private ?string $text;

    #[ORM\Column(options: ['comment' => 'Дата и время отправки сообщения в Telegram'])]
    private DateTimeImmutable $sentAt;

    #[ORM\Column(
        length: 32,
        enumType: ProcessedTelegramMessageStatus::class,
        options: ['comment' => 'Статус обработки: не обработан, успешно, с ошибкой'],
    )]
    private ProcessedTelegramMessageStatus $status;

    #[ORM\Column(
        type: Types::TEXT,
        nullable: true,
        options: ['comment' => 'Текст ошибки, если обработка завершилась с ошибкой'],
    )]
    private ?string $errorText;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        int $chatId,
        int $messageId,
        int $updateId,
        DateTimeImmutable $sentAt,
        ?string $userFirstName = null,
        ?string $userLastName = null,
        ?string $userNickname = null,
        ?string $text = null,
    ) {
        $this->id = new UuidV7();
        $this->chatId = $chatId;
        $this->messageId = $messageId;
        $this->updateId = $updateId;
        $this->sentAt = $sentAt;
        $this->userFirstName = $userFirstName;
        $this->userLastName = $userLastName;
        $this->userNickname = $userNickname;
        $this->text = $text;
        $this->status = ProcessedTelegramMessageStatus::Pending;
        $this->errorText = null;

        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markProcessedSuccess(): void
    {
        $this->status = ProcessedTelegramMessageStatus::ProcessedSuccess;
        $this->errorText = null;
    }

    /**
     * @throws EmptyProcessedTelegramMessageErrorTextException
     */
    public function markProcessedError(string $errorText): void
    {
        if (trim($errorText) === '') {
            throw new EmptyProcessedTelegramMessageErrorTextException(
                'Текст ошибки не может быть пустым',
            );
        }

        $this->status = ProcessedTelegramMessageStatus::ProcessedError;
        $this->errorText = $errorText;
    }

    public function getId(): UuidV7
    {
        return $this->id;
    }

    public function getUserFirstName(): ?string
    {
        return $this->userFirstName;
    }

    public function getUserLastName(): ?string
    {
        return $this->userLastName;
    }

    public function getUserNickname(): ?string
    {
        return $this->userNickname;
    }

    public function getChatId(): int
    {
        return $this->chatId;
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getUpdateId(): int
    {
        return $this->updateId;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getStatus(): ProcessedTelegramMessageStatus
    {
        return $this->status;
    }

    public function getErrorText(): ?string
    {
        return $this->errorText;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
