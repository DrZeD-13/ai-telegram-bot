<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820070215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add update_id to processed_telegram_message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE processed_telegram_message ADD update_id INT NOT NULL COMMENT \'Идентификатор Telegram update\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE processed_telegram_message DROP update_id');
    }
}
