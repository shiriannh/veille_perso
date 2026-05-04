<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable reference relations on entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry ADD media_type_reference_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD decision_type_reference_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD clickbait_level_reference_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entry ADD analysis_language_reference_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_2B219D70C7FF0957 ON entry (media_type_reference_id)');
        $this->addSql('CREATE INDEX IDX_2B219D701A3E4440 ON entry (decision_type_reference_id)');
        $this->addSql('CREATE INDEX IDX_2B219D706FE5B5CA ON entry (clickbait_level_reference_id)');
        $this->addSql('CREATE INDEX IDX_2B219D70BE0977BD ON entry (analysis_language_reference_id)');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D70E694E129 FOREIGN KEY (media_type_reference_id) REFERENCES media_type_reference (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D70A8FF4C66 FOREIGN KEY (decision_type_reference_id) REFERENCES decision_type_reference (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D708E6DDC55 FOREIGN KEY (clickbait_level_reference_id) REFERENCES clickbait_level_reference (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE entry ADD CONSTRAINT FK_2B219D70F165B8D2 FOREIGN KEY (analysis_language_reference_id) REFERENCES analysis_language_reference (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('UPDATE entry SET media_type_reference_id = media_type_reference.id FROM media_type_reference WHERE entry.media_type = media_type_reference.slug');
        $this->addSql('UPDATE entry SET decision_type_reference_id = decision_type_reference.id FROM decision_type_reference WHERE entry.decision = decision_type_reference.slug');
        $this->addSql('UPDATE entry SET clickbait_level_reference_id = clickbait_level_reference.id FROM clickbait_level_reference WHERE entry.clickbait_level = clickbait_level_reference.slug');
        $this->addSql('UPDATE entry SET analysis_language_reference_id = analysis_language_reference.id FROM analysis_language_reference WHERE entry.analysis_language = analysis_language_reference.slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entry DROP CONSTRAINT FK_2B219D70E694E129');
        $this->addSql('ALTER TABLE entry DROP CONSTRAINT FK_2B219D70A8FF4C66');
        $this->addSql('ALTER TABLE entry DROP CONSTRAINT FK_2B219D708E6DDC55');
        $this->addSql('ALTER TABLE entry DROP CONSTRAINT FK_2B219D70F165B8D2');
        $this->addSql('DROP INDEX IDX_2B219D70C7FF0957');
        $this->addSql('DROP INDEX IDX_2B219D701A3E4440');
        $this->addSql('DROP INDEX IDX_2B219D706FE5B5CA');
        $this->addSql('DROP INDEX IDX_2B219D70BE0977BD');
        $this->addSql('ALTER TABLE entry DROP media_type_reference_id');
        $this->addSql('ALTER TABLE entry DROP decision_type_reference_id');
        $this->addSql('ALTER TABLE entry DROP clickbait_level_reference_id');
        $this->addSql('ALTER TABLE entry DROP analysis_language_reference_id');
    }
}
