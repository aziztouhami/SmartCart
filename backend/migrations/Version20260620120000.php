<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deletion_requested_at to user for self-service account deletion with grace period';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN deletion_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN deletion_requested_at');
    }
}
