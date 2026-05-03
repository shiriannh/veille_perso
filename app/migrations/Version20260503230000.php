<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add media type origin for final media promotion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ADD media_type_origin VARCHAR(30) DEFAULT NULL');
        $this->addSql("UPDATE entry SET media_type_origin = CASE WHEN media_type = 'other' THEN 'unknown' ELSE 'manual' END WHERE media_type_origin IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry DROP media_type_origin');
    }
}
