<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable reference relations on sources';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ADD source_type_reference_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE source ADD fetch_mode_reference_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_5F8A7F73AE2416B7 ON source (source_type_reference_id)');
        $this->addSql('CREATE INDEX IDX_5F8A7F737E5EECB5 ON source (fetch_mode_reference_id)');
        $this->addSql('ALTER TABLE source ADD CONSTRAINT FK_5F8A7F731803D945 FOREIGN KEY (source_type_reference_id) REFERENCES source_type_reference (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE source ADD CONSTRAINT FK_5F8A7F73F6AFBF1E FOREIGN KEY (fetch_mode_reference_id) REFERENCES fetch_mode_reference (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('UPDATE source SET source_type_reference_id = source_type_reference.id FROM source_type_reference WHERE source.type = source_type_reference.slug');
        $this->addSql('UPDATE source SET fetch_mode_reference_id = fetch_mode_reference.id FROM fetch_mode_reference WHERE source.fetch_mode = fetch_mode_reference.slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP CONSTRAINT FK_5F8A7F731803D945');
        $this->addSql('ALTER TABLE source DROP CONSTRAINT FK_5F8A7F73F6AFBF1E');
        $this->addSql('DROP INDEX IDX_5F8A7F73AE2416B7');
        $this->addSql('DROP INDEX IDX_5F8A7F737E5EECB5');
        $this->addSql('ALTER TABLE source DROP source_type_reference_id');
        $this->addSql('ALTER TABLE source DROP fetch_mode_reference_id');
    }
}
