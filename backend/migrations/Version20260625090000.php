<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create guest_event (anonymous session tracking) and product_relation (precomputed recommendations) tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE guest_event (
            id SERIAL NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            product_id INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE guest_event ADD CONSTRAINT fk_guest_event_product FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_guest_event_product ON guest_event (product_id)');
        $this->addSql('CREATE INDEX idx_guest_event_session ON guest_event (session_id)');

        $this->addSql('CREATE TABLE product_relation (
            id SERIAL NOT NULL,
            product_id INT NOT NULL,
            related_product_id INT NOT NULL,
            score DOUBLE PRECISION NOT NULL,
            computed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE product_relation ADD CONSTRAINT fk_product_relation_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_relation ADD CONSTRAINT fk_product_relation_related FOREIGN KEY (related_product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_related ON product_relation (product_id, related_product_id)');
        $this->addSql('CREATE INDEX idx_product_relation_product_score ON product_relation (product_id, score)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_relation');
        $this->addSql('DROP TABLE guest_event');
    }
}
