<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preferred category/brand ids to user, create user_recommendation table for the logged-in hybrid recommender';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN preferred_category_ids JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE "user" ADD COLUMN preferred_brand_ids JSON NOT NULL DEFAULT \'[]\'');

        $this->addSql('CREATE TABLE user_recommendation (
            id SERIAL NOT NULL,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            score DOUBLE PRECISION NOT NULL,
            source VARCHAR(20) NOT NULL,
            computed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE user_recommendation ADD CONSTRAINT fk_user_recommendation_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_recommendation ADD CONSTRAINT fk_user_recommendation_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_recommendation_product ON user_recommendation (user_id, product_id)');
        $this->addSql('CREATE INDEX idx_user_recommendation_user_score ON user_recommendation (user_id, score)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_recommendation');
        $this->addSql('ALTER TABLE "user" DROP COLUMN preferred_category_ids');
        $this->addSql('ALTER TABLE "user" DROP COLUMN preferred_brand_ids');
    }
}
