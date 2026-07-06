<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create chat_message_log table (chatbot conversation log + rate limiting)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE chat_message_log (
            id SERIAL NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            role VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_chat_message_log_session_id ON chat_message_log (session_id)');
        $this->addSql('CREATE INDEX idx_chat_message_log_created_at ON chat_message_log (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE chat_message_log');
    }
}
