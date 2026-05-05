<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505073129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1.1 — création de la table chantier (domaine Chantier)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE chantier (
              id UUID NOT NULL,
              adresse_rue VARCHAR(255) NOT NULL,
              adresse_code_postal VARCHAR(20) NOT NULL,
              adresse_ville VARCHAR(255) NOT NULL,
              adresse_pays CHAR(2) NOT NULL,
              surface_m2 NUMERIC(10, 2) DEFAULT NULL,
              statut VARCHAR(32) NOT NULL,
              cree_le TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                modifie_le TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_chantier_statut ON chantier (statut)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE chantier');
    }
}
