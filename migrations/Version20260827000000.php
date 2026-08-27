<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create conversation_message table for Telegram AI agent dialog history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversation_message (id BINARY(16) NOT NULL, chat_id BIGINT NOT NULL COMMENT \'Идентификатор чата Telegram\', role VARCHAR(16) NOT NULL COMMENT \'Роль сообщения: user или assistant\', content LONGTEXT DEFAULT NULL COMMENT \'Текст сообщения диалога\', created_at DATETIME NOT NULL, INDEX idx_conversation_message_chat (chat_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` COMMENT = \'История диалога Telegram-чата с нейросетью\' ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE conversation_message');
    }
}
