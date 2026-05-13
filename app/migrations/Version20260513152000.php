<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add structured import run details for admission summaries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_run ADD details JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_run DROP details');
    }
}
