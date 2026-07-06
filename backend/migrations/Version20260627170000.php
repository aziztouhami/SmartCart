<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add seasonal_months tag to category (manual seasonal recommendation boost)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD seasonal_months JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP seasonal_months');
    }
}
