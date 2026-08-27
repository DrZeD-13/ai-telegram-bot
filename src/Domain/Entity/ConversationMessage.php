<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
#[ORM\Table(
    name: 'conversation_message',
    options: ['comment' => 'История сообщений сессии диалога с нейросетью'],
)]
#[ORM\Index(name: 'idx_conversation_message_chat', columns: ['chat_id', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class ConversationMessage
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private UuidV7 $id;

    #[ORM\Column(type: UuidType::NAME, options: ['comment' => 'Идентификатор сессии диалога с нейросетью'])]
    private Uuid $chatId;

    #[ORM\Column(length: 16, options: ['comment' => 'Роль сообщения: user или assistant'])]
    private string $role;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => 'Текст сообщения диалога'])]
    private ?string $content;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $chatId,
        string $role,
        ?string $content,
    ) {
        $this->id = new UuidV7();
        $this->chatId = $chatId;
        $this->role = $role;
        $this->content = $content;
        $this->createdAt = new DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): UuidV7
    {
        return $this->id;
    }

    public function getChatId(): Uuid
    {
        return $this->chatId;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
