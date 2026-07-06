<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create category_seasonal_score (learned seasonality index, not yet wired into serving)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category_seasonal_score (
            id SERIAL NOT NULL,
            category_id INT NOT NULL,
            month INT NOT NULL,
            score DOUBLE PRECISION NOT NULL,
            computed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE category_seasonal_score ADD CONSTRAINT fk_category_seasonal_score_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_month ON category_seasonal_score (category_id, month)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE category_seasonal_score');
    }
}
