<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820065054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create processed_telegram_message table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE processed_telegram_message (id BINARY(16) NOT NULL, user_first_name VARCHAR(255) DEFAULT NULL COMMENT \'Имя отправителя в Telegram\', user_last_name VARCHAR(255) DEFAULT NULL COMMENT \'Фамилия отправителя в Telegram\', user_nickname VARCHAR(64) DEFAULT NULL COMMENT \'Username отправителя в Telegram\', chat_id BIGINT NOT NULL COMMENT \'Идентификатор чата Telegram\', message_id INT NOT NULL COMMENT \'Идентификатор сообщения в чате Telegram\', text LONGTEXT DEFAULT NULL COMMENT \'Текст входящего сообщения\', sent_at DATETIME NOT NULL COMMENT \'Дата и время отправки сообщения в Telegram\', status VARCHAR(32) NOT NULL COMMENT \'Статус обработки: не обработан, успешно, с ошибкой\', error_text LONGTEXT DEFAULT NULL COMMENT \'Текст ошибки, если обработка завершилась с ошибкой\', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_processed_telegram_message_chat_message (chat_id, message_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` COMMENT = \'Входящие сообщения Telegram и статус их обработки\' ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE processed_telegram_message');
    }
}
