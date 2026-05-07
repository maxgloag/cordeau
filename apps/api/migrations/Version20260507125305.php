<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260507125305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute tables utilisateur et auth_token, lie chantier à son propriétaire.';
    }

    public function up(Schema $schema): void
    {
        // Les chantiers de dev sans propriétaire sont supprimés (Phase 1 pré-auth, pas de données critiques).
        $this->addSql('DELETE FROM chantier');
        $this->addSql('CREATE TABLE auth_token (id UUID NOT NULL, selector VARCHAR(32) NOT NULL, verifier_hash VARCHAR(255) NOT NULL, refresh_token_hash VARCHAR(255) DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, refresh_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, device_info VARCHAR(255) DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cree_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, utilisateur_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9315F04EFB88E14F ON auth_token (utilisateur_id)');
        $this->addSql('CREATE INDEX idx_auth_token_selector ON auth_token (selector)');
        $this->addSql('CREATE TABLE utilisateur (id UUID NOT NULL, email VARCHAR(255) NOT NULL, mot_de_passe_hash VARCHAR(255) NOT NULL, cree_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modifie_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_utilisateur_email ON utilisateur (email)');
        $this->addSql('ALTER TABLE auth_token ADD CONSTRAINT FK_9315F04EFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE chantier ADD proprietaire_id UUID NOT NULL');
        $this->addSql('ALTER TABLE chantier ADD CONSTRAINT FK_636F27F676C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_636F27F676C50E4A ON chantier (proprietaire_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE auth_token DROP CONSTRAINT FK_9315F04EFB88E14F');
        $this->addSql('DROP TABLE auth_token');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('ALTER TABLE chantier DROP CONSTRAINT FK_636F27F676C50E4A');
        $this->addSql('DROP INDEX IDX_636F27F676C50E4A');
        $this->addSql('ALTER TABLE chantier DROP proprietaire_id');
    }
}
