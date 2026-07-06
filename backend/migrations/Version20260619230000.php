<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification and marketing opt-in fields to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN is_verified BOOLEAN NOT NULL DEFAULT true');
        $this->addSql('ALTER TABLE "user" ADD COLUMN verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN marketing_opt_in BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_verification_token ON "user" (verification_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_verification_token');
        $this->addSql('ALTER TABLE "user" DROP COLUMN is_verified');
        $this->addSql('ALTER TABLE "user" DROP COLUMN verification_token');
        $this->addSql('ALTER TABLE "user" DROP COLUMN marketing_opt_in');
    }
}
