<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add post-RSS analysis fields to entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ADD normalized_title TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD normalized_content TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD relevance_score INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD clickbait_score INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD decision VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD decision_reason TEXT DEFAULT NULL');
        $this->addSql("ALTER TABLE entry ADD matched_positive_keywords JSON NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE entry ADD matched_negative_keywords JSON NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE entry ADD clickbait_signals JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE entry ADD clickbait_level VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD analyzed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD ai_analyzed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD ai_model VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD ai_raw_result JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD analysis_version VARCHAR(50) DEFAULT NULL');
        $this->addSql("ALTER TABLE entry ADD analysis_status VARCHAR(255) NOT NULL DEFAULT 'pending'");
        $this->addSql('ALTER TABLE entry ALTER matched_positive_keywords DROP DEFAULT');
        $this->addSql('ALTER TABLE entry ALTER matched_negative_keywords DROP DEFAULT');
        $this->addSql('ALTER TABLE entry ALTER clickbait_signals DROP DEFAULT');
        $this->addSql('ALTER TABLE entry ALTER analysis_status DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry DROP normalized_title');
        $this->addSql('ALTER TABLE entry DROP normalized_content');
        $this->addSql('ALTER TABLE entry DROP relevance_score');
        $this->addSql('ALTER TABLE entry DROP clickbait_score');
        $this->addSql('ALTER TABLE entry DROP decision');
        $this->addSql('ALTER TABLE entry DROP decision_reason');
        $this->addSql('ALTER TABLE entry DROP matched_positive_keywords');
        $this->addSql('ALTER TABLE entry DROP matched_negative_keywords');
        $this->addSql('ALTER TABLE entry DROP clickbait_signals');
        $this->addSql('ALTER TABLE entry DROP clickbait_level');
        $this->addSql('ALTER TABLE entry DROP analyzed_at');
        $this->addSql('ALTER TABLE entry DROP ai_analyzed_at');
        $this->addSql('ALTER TABLE entry DROP ai_model');
        $this->addSql('ALTER TABLE entry DROP ai_raw_result');
        $this->addSql('ALTER TABLE entry DROP analysis_version');
        $this->addSql('ALTER TABLE entry DROP analysis_status');
    }
}
