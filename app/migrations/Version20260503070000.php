<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align Doctrine-generated index names for Entry and Review.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_2b219d70f675f31b RENAME TO IDX_2B219D70953C1C61');
        $this->addSql('ALTER INDEX uniq_794381caba364942 RENAME TO UNIQ_794381C6BA364942');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_2B219D70953C1C61 RENAME TO idx_2b219d70f675f31b');
        $this->addSql('ALTER INDEX UNIQ_794381C6BA364942 RENAME TO uniq_794381caba364942');
    }
}
