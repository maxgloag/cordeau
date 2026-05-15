<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260515071217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 2.5 — lier chantier à client via VO ClientRef (client_id nullable + client_nom_cache)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chantier ADD client_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE chantier ADD client_nom_cache VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_chantier_client ON chantier (client_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_chantier_client');
        $this->addSql('ALTER TABLE chantier DROP client_id');
        $this->addSql('ALTER TABLE chantier DROP client_nom_cache');
    }
}
