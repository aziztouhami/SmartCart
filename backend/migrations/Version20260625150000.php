<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cold_start_recommendation table — the global "no personalization signal yet" fallback list';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cold_start_recommendation (
            id SERIAL NOT NULL,
            product_id INT NOT NULL,
            score DOUBLE PRECISION NOT NULL,
            computed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE cold_start_recommendation ADD CONSTRAINT fk_cold_start_recommendation_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_cold_start_recommendation_score ON cold_start_recommendation (score)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cold_start_recommendation');
    }
}
