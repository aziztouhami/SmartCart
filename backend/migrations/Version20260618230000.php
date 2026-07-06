<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create promotion table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE promotion (
            id SERIAL NOT NULL,
            product_id INT DEFAULT NULL,
            brand_id INT DEFAULT NULL,
            type VARCHAR(20) NOT NULL,
            discount_type VARCHAR(20) NOT NULL,
            percentage NUMERIC(5, 2) DEFAULT NULL,
            fixed_price NUMERIC(10, 2) DEFAULT NULL,
            start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE promotion ADD CONSTRAINT fk_promotion_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE promotion ADD CONSTRAINT fk_promotion_brand FOREIGN KEY (brand_id) REFERENCES brand (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_promotion_product ON promotion (product_id)');
        $this->addSql('CREATE INDEX idx_promotion_brand ON promotion (brand_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE promotion DROP CONSTRAINT fk_promotion_product');
        $this->addSql('ALTER TABLE promotion DROP CONSTRAINT fk_promotion_brand');
        $this->addSql('DROP TABLE promotion');
    }
}
