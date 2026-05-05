<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source import priority';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ADD import_priority INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE source ALTER import_priority DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP import_priority');
    }
}
