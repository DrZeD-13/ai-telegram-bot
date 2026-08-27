<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
#[ORM\Table(
    name: 'conversation_session',
    options: ['comment' => 'Сессия диалога с нейросетью; для Telegram хранит текущий активный чат'],
)]
#[ORM\Index(name: 'idx_conversation_session_telegram_active', columns: ['telegram_chat_id', 'last_active_at'])]
#[ORM\HasLifecycleCallbacks]
class ConversationSession
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private UuidV7 $id;

    #[ORM\Column(
        type: Types::BIGINT,
        nullable: true,
        options: ['comment' => 'Идентификатор чата Telegram; пусто для консольной сессии'],
    )]
    private ?int $telegramChatId;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $lastActiveAt;

    public function __construct(?int $telegramChatId = null)
    {
        $now = new DateTimeImmutable();
        $this->id = new UuidV7();
        $this->telegramChatId = $telegramChatId;
        $this->createdAt = $now;
        $this->lastActiveAt = $now;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->lastActiveAt = $now;
    }

    public function getId(): UuidV7
    {
        return $this->id;
    }

    public function getTelegramChatId(): ?int
    {
        return $this->telegramChatId;
    }

    public function belongsToTelegramChat(int $telegramChatId): bool
    {
        return $this->telegramChatId === $telegramChatId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActiveAt(): DateTimeImmutable
    {
        return $this->lastActiveAt;
    }

    public function markActive(): void
    {
        $this->lastActiveAt = new DateTimeImmutable();
    }
}
