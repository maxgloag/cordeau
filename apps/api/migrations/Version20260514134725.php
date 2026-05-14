<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260514134725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 2.1 — create client table (mode CRUD léger, FK proprietaire→utilisateur)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id UUID NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, adresse_rue VARCHAR(255) NOT NULL, adresse_code_postal VARCHAR(20) NOT NULL, adresse_ville VARCHAR(255) NOT NULL, adresse_pays CHAR(2) NOT NULL, notes TEXT DEFAULT NULL, cree_le TIMESTAMP(0) WITH TIME ZONE NOT NULL, modifie_le TIMESTAMP(0) WITH TIME ZONE NOT NULL, proprietaire_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_client_proprietaire ON client (proprietaire_id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C744045576C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP CONSTRAINT FK_C744045576C50E4A');
        $this->addSql('DROP TABLE client');
    }
}
