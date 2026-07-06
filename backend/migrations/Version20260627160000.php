<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split product_relation into similar/complementary types for per-product recommendations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product_relation ADD type VARCHAR(20) NOT NULL DEFAULT 'similar'");
        $this->addSql('DROP INDEX uniq_product_related');
        $this->addSql('DROP INDEX idx_product_relation_product_score');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_related ON product_relation (product_id, related_product_id, type)');
        $this->addSql('CREATE INDEX idx_product_relation_product_score ON product_relation (product_id, type, score)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_product_related');
        $this->addSql('DROP INDEX idx_product_relation_product_score');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_related ON product_relation (product_id, related_product_id)');
        $this->addSql('CREATE INDEX idx_product_relation_product_score ON product_relation (product_id, score)');
        $this->addSql('ALTER TABLE product_relation DROP type');
    }
}
