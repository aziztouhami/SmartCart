<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create brand table and add brand_id column to product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE brand (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            image VARCHAR(500) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE product ADD COLUMN brand_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT fk_product_brand FOREIGN KEY (brand_id) REFERENCES brand (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_product_brand ON product (brand_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP CONSTRAINT fk_product_brand');
        $this->addSql('DROP INDEX idx_product_brand');
        $this->addSql('ALTER TABLE product DROP COLUMN brand_id');
        $this->addSql('DROP TABLE brand');
    }
}
