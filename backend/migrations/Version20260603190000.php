<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: SmartCart Initial Schema
 * Created: 2026-06-03
 * 
 * Creates all tables for:
 * - User (authentication & accounts)
 * - Product (catalog items)
 * - Category (product categories with hierarchy)
 * - Order (customer orders)
 * - OrderItem (order line items)
 * - Review (product reviews)
 * - Interaction (user behavior tracking for AI recommendations)
 */
final class Version20260603190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial SmartCart database schema with all entities';
    }

    public function up(Schema $schema): void
    {
        // Create User table
        $this->addSql('CREATE TABLE "user" (
            id SERIAL NOT NULL,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            roles JSON NOT NULL,
            created_at TIMESTAMP(0) NOT NULL,
            updated_at TIMESTAMP(0) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');

        // Create Category table (self-referencing for hierarchy)
        $this->addSql('CREATE TABLE category (
            id SERIAL NOT NULL,
            parent_id INT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            image VARCHAR(255),
            created_at TIMESTAMP(0) NOT NULL,
            updated_at TIMESTAMP(0) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64C19C1989D9B62 ON category (slug)');
        $this->addSql('CREATE INDEX IDX_64C19C1727ACA70 ON category (parent_id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE CASCADE');

        // Create Product table
        $this->addSql('CREATE TABLE product (
            id SERIAL NOT NULL,
            category_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price NUMERIC(10, 2) NOT NULL,
            stock INT NOT NULL,
            slug VARCHAR(255) NOT NULL,
            images TEXT,
            created_at TIMESTAMP(0) NOT NULL,
            updated_at TIMESTAMP(0) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04AD989D9B62 ON product (slug)');
        $this->addSql('CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE RESTRICT');

        // Create Order table
        $this->addSql('CREATE TABLE "order" (
            id SERIAL NOT NULL,
            user_id INT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'pending\',
            total_amount NUMERIC(10, 2) NOT NULL,
            shipping_address TEXT,
            created_at TIMESTAMP(0) NOT NULL,
            updated_at TIMESTAMP(0) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_F5299398A76ED395 ON "order" (user_id)');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');

        // Create OrderItem table (line items in orders)
        $this->addSql('CREATE TABLE order_item (
            id SERIAL NOT NULL,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price NUMERIC(10, 2) NOT NULL,
            created_at TIMESTAMP(0) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_6FA735A78D9F6D38 ON order_item (order_id)');
        $this->addSql('CREATE INDEX IDX_6FA735A74584665A ON order_item (product_id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_6FA735A78D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_6FA735A74584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE RESTRICT');

        // Create Review table
        $this->addSql('CREATE TABLE review (
            id SERIAL NOT NULL,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            rating INT NOT NULL,
            comment TEXT,
            created_at TIMESTAMP(0) NOT NULL,
            updated_at TIMESTAMP(0) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_794381C6A76ED395 ON review (user_id)');
        $this->addSql('CREATE INDEX IDX_794381C64584665A ON review (product_id)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C64584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        // Create Interaction table (user behavior tracking for recommendations)
        $this->addSql('CREATE TABLE interaction (
            id SERIAL NOT NULL,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            value INT,
            created_at TIMESTAMP(0) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_378DFDA7A76ED395 ON interaction (user_id)');
        $this->addSql('CREATE INDEX IDX_378DFDA74584665A ON interaction (product_id)');
        $this->addSql('ALTER TABLE interaction ADD CONSTRAINT FK_378DFDA7A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interaction ADD CONSTRAINT FK_378DFDA74584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop all foreign keys and tables in reverse order
        $this->addSql('ALTER TABLE interaction DROP CONSTRAINT FK_378DFDA74584665A');
        $this->addSql('ALTER TABLE interaction DROP CONSTRAINT FK_378DFDA7A76ED395');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C64584665A');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6A76ED395');
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT FK_6FA735A74584665A');
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT FK_6FA735A78D9F6D38');
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_64C19C1727ACA70');

        // Drop tables
        $this->addSql('DROP TABLE interaction');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE "order"');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE "user"');
    }
}
