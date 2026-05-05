<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add synthesis report criteria and review links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE synthesis_report_review (synthesis_report_id INT NOT NULL, review_id INT NOT NULL, PRIMARY KEY(synthesis_report_id, review_id))');
        $this->addSql('CREATE INDEX IDX_FDEE0625BC6545F9 ON synthesis_report_review (synthesis_report_id)');
        $this->addSql('CREATE INDEX IDX_FDEE06253E2E969B ON synthesis_report_review (review_id)');
        $this->addSql('ALTER TABLE synthesis_report_review ADD CONSTRAINT FK_7AE84E91BC6545F9 FOREIGN KEY (synthesis_report_id) REFERENCES synthesis_report (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE synthesis_report_review ADD CONSTRAINT FK_7AE84E91562B4D5D FOREIGN KEY (review_id) REFERENCES review (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE synthesis_report ADD criteria JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE synthesis_report DROP criteria');
        $this->addSql('ALTER TABLE synthesis_report_review DROP CONSTRAINT FK_7AE84E91BC6545F9');
        $this->addSql('ALTER TABLE synthesis_report_review DROP CONSTRAINT FK_7AE84E91562B4D5D');
        $this->addSql('DROP TABLE synthesis_report_review');
    }
}
