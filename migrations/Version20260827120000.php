<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260827120000 extends AbstractMigration
{
    private const string LEGACY_NAMESPACE = '8f1c2e6a-4b7d-5e91-a3c0-9d2b6f8e4a17';

    public function getDescription(): string
    {
        return 'Store conversation sessions as UUID; keep history on /new';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversation_session (id BINARY(16) NOT NULL, telegram_chat_id BIGINT DEFAULT NULL COMMENT \'Идентификатор чата Telegram; пусто для консольной сессии\', created_at DATETIME NOT NULL, last_active_at DATETIME NOT NULL, INDEX idx_conversation_session_telegram_active (telegram_chat_id, last_active_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` COMMENT = \'Сессия диалога с нейросетью; для Telegram хранит текущий активный чат\'');
        $this->addSql('ALTER TABLE conversation_message ADD chat_id_uuid BINARY(16) DEFAULT NULL COMMENT \'Идентификатор сессии диалога с нейросетью\'');
    }

    public function postUp(Schema $schema): void
    {
        $legacyIds = $this->connection->fetchFirstColumn('SELECT DISTINCT chat_id FROM conversation_message');
        $namespace = Uuid::fromString(self::LEGACY_NAMESPACE);
        foreach ($legacyIds as $legacyId) {
            $oldId = (int) $legacyId;
            $sessionId = Uuid::v5($namespace, 'legacy-chat:' . $oldId);
            $binary = $sessionId->toBinary();
            $this->connection->executeStatement(
                'UPDATE conversation_message SET chat_id_uuid = ? WHERE chat_id = ?',
                [$binary, $oldId],
                [ParameterType::BINARY, ParameterType::INTEGER],
            );
            $this->connection->executeStatement(
                'INSERT INTO conversation_session (id, telegram_chat_id, created_at, last_active_at) VALUES (?, ?, NOW(), NOW())',
                [$binary, $oldId],
                [ParameterType::BINARY, ParameterType::INTEGER],
            );
        }

        $this->connection->executeStatement('ALTER TABLE conversation_message DROP INDEX idx_conversation_message_chat');
        $this->connection->executeStatement('ALTER TABLE conversation_message DROP COLUMN chat_id');
        $this->connection->executeStatement('ALTER TABLE conversation_message CHANGE chat_id_uuid chat_id BINARY(16) NOT NULL COMMENT \'Идентификатор сессии диалога с нейросетью\'');
        $this->connection->executeStatement('CREATE INDEX idx_conversation_message_chat ON conversation_message (chat_id, created_at)');
        $this->connection->executeStatement('ALTER TABLE conversation_message COMMENT = \'История сообщений сессии диалога с нейросетью\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE conversation_session');
        $this->addSql('ALTER TABLE conversation_message DROP INDEX idx_conversation_message_chat');
        $this->addSql('ALTER TABLE conversation_message ADD chat_id_int BIGINT DEFAULT NULL COMMENT \'Идентификатор чата Telegram\'');
        $this->addSql('ALTER TABLE conversation_message DROP COLUMN chat_id');
        $this->addSql('ALTER TABLE conversation_message CHANGE chat_id_int chat_id BIGINT NOT NULL COMMENT \'Идентификатор чата Telegram\'');
        $this->addSql('CREATE INDEX idx_conversation_message_chat ON conversation_message (chat_id, created_at)');
    }
}
