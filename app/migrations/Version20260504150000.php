<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add review workflow status, decision date and next action';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE review ADD status VARCHAR(255) DEFAULT 'to_complete' NOT NULL");
        $this->addSql('ALTER TABLE review ADD decision_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE review ADD next_action VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE review SET status = 'draft' WHERE is_draft = TRUE");
        $this->addSql("UPDATE review SET status = 'completed', decision_at = updated_at WHERE is_draft = FALSE AND verdict IN ('must_have', 'recommended', 'wait', 'skip')");
        $this->addSql('ALTER TABLE review ALTER status DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review DROP status');
        $this->addSql('ALTER TABLE review DROP decision_at');
        $this->addSql('ALTER TABLE review DROP next_action');
    }
}
