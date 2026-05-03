<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prevent silent Review deletion when deleting an Entry.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381CABA364942');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381CABA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381CABA364942');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381CABA364942 FOREIGN KEY (entry_id) REFERENCES entry (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
