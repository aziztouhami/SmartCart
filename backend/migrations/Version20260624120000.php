<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product_type and product_type_attribute tables, add product_type_id and attributes columns to product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_type (
            id SERIAL NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_type_name ON product_type (name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_type_slug ON product_type (slug)');

        $this->addSql('CREATE TABLE product_type_attribute (
            id SERIAL NOT NULL,
            product_type_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            data_type VARCHAR(20) NOT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            options JSON DEFAULT NULL,
            required BOOLEAN NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE product_type_attribute ADD CONSTRAINT fk_pta_product_type FOREIGN KEY (product_type_id) REFERENCES product_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_pta_product_type ON product_type_attribute (product_type_id)');

        $this->addSql('ALTER TABLE product ADD COLUMN product_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD COLUMN attributes JSON NOT NULL DEFAULT \'{}\'');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT fk_product_product_type FOREIGN KEY (product_type_id) REFERENCES product_type (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_product_product_type ON product (product_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP CONSTRAINT fk_product_product_type');
        $this->addSql('DROP INDEX idx_product_product_type');
        $this->addSql('ALTER TABLE product DROP COLUMN product_type_id');
        $this->addSql('ALTER TABLE product DROP COLUMN attributes');

        $this->addSql('DROP TABLE product_type_attribute');
        $this->addSql('DROP TABLE product_type');
    }
}
