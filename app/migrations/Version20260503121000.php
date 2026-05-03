<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align RSS import schema with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_2b219d70735d12f7');
        $this->addSql('DROP INDEX idx_2b219d706270c499');
        $this->addSql('DROP INDEX idx_2b219d70315134fd');
        $this->addSql('ALTER INDEX idx_bbb6eacf953c1c61 RENAME TO IDX_C41B0440953C1C61');
        $this->addSql('ALTER TABLE source ALTER fetch_mode DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ALTER fetch_mode SET DEFAULT \'manual\'');
        $this->addSql('ALTER INDEX IDX_C41B0440953C1C61 RENAME TO idx_bbb6eacf953c1c61');
        $this->addSql('CREATE INDEX idx_2b219d70735d12f7 ON entry (external_id)');
        $this->addSql('CREATE INDEX idx_2b219d706270c499 ON entry (source_hash)');
        $this->addSql('CREATE INDEX idx_2b219d70315134fd ON entry (canonical_url)');
    }
}
