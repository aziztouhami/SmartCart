<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add phone to user; create address and favorite tables';
    }

    public function up(Schema $schema): void
    {
        // Add phone column to user table
        $this->addSql('ALTER TABLE "user" ADD phone VARCHAR(20) DEFAULT NULL');

        // Create address table
        $this->addSql('CREATE TABLE address (
            id SERIAL NOT NULL,
            user_id INT NOT NULL,
            label VARCHAR(100) NOT NULL,
            street VARCHAR(255) NOT NULL,
            city VARCHAR(100) NOT NULL,
            postal_code VARCHAR(20) DEFAULT NULL,
            country VARCHAR(100) NOT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_address_user ON address (user_id)');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT fk_address_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Create favorite table
        $this->addSql('CREATE TABLE favorite (
            id SERIAL NOT NULL,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_favorite_user ON favorite (user_id)');
        $this->addSql('CREATE INDEX idx_favorite_product ON favorite (product_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_user_product_favorite ON favorite (user_id, product_id)');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT fk_favorite_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT fk_favorite_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP CONSTRAINT fk_address_user');
        $this->addSql('DROP TABLE address');

        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT fk_favorite_user');
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT fk_favorite_product');
        $this->addSql('DROP TABLE favorite');

        $this->addSql('ALTER TABLE "user" DROP COLUMN phone');
    }
}
