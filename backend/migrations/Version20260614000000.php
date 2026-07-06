<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add latitude and longitude columns to address table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE address ADD longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP COLUMN latitude');
        $this->addSql('ALTER TABLE address DROP COLUMN longitude');
    }
}
