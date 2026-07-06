<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for frequently-filtered columns: order(status, created_at), interaction(type), guest_event(created_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_order_status_created ON "order" (status, created_at)');
        $this->addSql('CREATE INDEX idx_interaction_type ON interaction (type)');
        $this->addSql('CREATE INDEX idx_guest_event_created ON guest_event (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_order_status_created');
        $this->addSql('DROP INDEX idx_interaction_type');
        $this->addSql('DROP INDEX idx_guest_event_created');
    }
}
