<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tag role and mark known noisy auto tags inactive.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tag ADD role VARCHAR(255) NOT NULL DEFAULT 'contextual'");
        $this->addSql("UPDATE tag SET role = 'pivot' WHERE slug IN ('science-fiction', 'sf', 'fantasy', 'fantastique', 'space-opera', 'cyberpunk', 'manga', 'anime', 'jdr', 'figurines', 'jeu-video', 'warhammer-40k', 'dungeons-and-dragons', 'pathfinder', 'shadowrun')");
        $this->addSql("UPDATE tag SET role = 'deprioritize' WHERE slug IN ('battle-pass', 'microtransaction', 'monetisation', 'crowdfunding', 'gamefound')");
        $this->addSql("UPDATE tag SET role = 'editorial_format' WHERE slug IN ('festival', 'trailer', 'interview')");
        $this->addSql("UPDATE tag SET role = 'noise', is_active = false, is_interest_related = false, notes = CONCAT(COALESCE(notes, ''), E'\nMarque automatiquement comme bruit editorial par la migration V1.7.') WHERE slug IN ('nos-conseils', 'nouveau', 'nouveaute', 'festival-de-cannes', 'evenement-fnac-gratuit', 'rpg-party', 'gamefound', 'douglas-kennedy', 'virginie-grimaldi')");
        $this->addSql('ALTER TABLE tag ALTER role DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag DROP role');
    }
}
