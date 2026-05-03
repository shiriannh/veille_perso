<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add detected tags and detected media type to entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE entry ADD detected_tags JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE entry ADD detected_media_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ALTER detected_tags DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry DROP detected_tags');
        $this->addSql('ALTER TABLE entry DROP detected_media_type');
    }
}
