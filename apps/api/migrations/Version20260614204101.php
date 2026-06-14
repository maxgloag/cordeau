<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 6 — Verticale Lots / Tâches (ADR 0015, ADR 0024).
 * Migration additive : tables `lot` et `tache`, aucune modification de `chantier`.
 * FK avec ON DELETE CASCADE (chantier → lots → tâches).
 */
final class Version20260614204101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 6 : tables lot et tache (additive)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lot (id UUID NOT NULL, chantier_id UUID NOT NULL, nom VARCHAR(255) NOT NULL, mode VARCHAR(16) NOT NULL, estimation NUMERIC(12, 2) DEFAULT NULL, reel NUMERIC(12, 2) DEFAULT NULL, ordre INT NOT NULL, imprevus JSON NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_lot_chantier_ordre ON lot (chantier_id, ordre)');
        $this->addSql('CREATE TABLE tache (id UUID NOT NULL, lot_id UUID NOT NULL, libelle VARCHAR(255) NOT NULL, faite BOOLEAN NOT NULL, faite_le TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, ordre INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_tache_lot_ordre ON tache (lot_id, ordre)');
        $this->addSql('ALTER TABLE lot ADD CONSTRAINT fk_lot_chantier FOREIGN KEY (chantier_id) REFERENCES chantier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT fk_tache_lot FOREIGN KEY (lot_id) REFERENCES lot (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tache DROP CONSTRAINT fk_tache_lot');
        $this->addSql('ALTER TABLE lot DROP CONSTRAINT fk_lot_chantier');
        $this->addSql('DROP TABLE tache');
        $this->addSql('DROP TABLE lot');
    }
}
