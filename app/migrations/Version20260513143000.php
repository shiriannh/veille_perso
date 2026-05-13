<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add raw detected terms to Entry and expand hard tag neutralization.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE entry ADD raw_detected_terms JSON NOT NULL DEFAULT '[]'");
        $this->addSql("UPDATE tag SET role = 'noise', is_active = false, is_interest_related = false, notes = CONCAT(COALESCE(notes, ''), E'\nNeutralise a l entree par le sas metier V1.7.') WHERE slug IN ('nouvelle', 'nouvelles', 'novella', 'essai', 'preface', 'postface', 'interview', 'podcast', 'actualites', 'actualite', 'nouveau', 'nos-conseils', 'festival', 'prix-litteraire', 'evenement', 'blockbuster', 'download')");
        $this->addSql('ALTER TABLE entry ALTER raw_detected_terms DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry DROP raw_detected_terms');
    }
}
