<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add leslibraires.fr catalog fetch mode reference.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO fetch_mode_reference (name, slug, is_active, created_at, updated_at) VALUES ('Catalogue leslibraires.fr', 'leslibraires_catalog', true, NOW(), NOW()) ON CONFLICT (slug) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM fetch_mode_reference WHERE slug = 'leslibraires_catalog'");
    }
}
