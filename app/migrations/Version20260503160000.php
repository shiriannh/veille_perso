<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add detailed analysis dimensions to entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ADD media_detection_confidence INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD thematic_score INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD editorial_quality_score INT DEFAULT NULL');
        $this->addSql("ALTER TABLE entry ADD analysis_signals JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE entry ADD analysis_language VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ALTER analysis_signals DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry DROP media_detection_confidence');
        $this->addSql('ALTER TABLE entry DROP thematic_score');
        $this->addSql('ALTER TABLE entry DROP editorial_quality_score');
        $this->addSql('ALTER TABLE entry DROP analysis_signals');
        $this->addSql('ALTER TABLE entry DROP analysis_language');
    }
}
