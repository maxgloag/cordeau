<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608205427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE photo (id UUID NOT NULL, chantier_id UUID NOT NULL, lot_id UUID DEFAULT NULL, tache_id UUID DEFAULT NULL, remote_key VARCHAR(500) NOT NULL, photo_url VARCHAR(500) NOT NULL, thumbnail_url VARCHAR(500) DEFAULT NULL, uploade_le TIMESTAMP(0) WITH TIME ZONE NOT NULL, cree_le TIMESTAMP(0) WITH TIME ZONE NOT NULL, proprietaire_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_photo_chantier ON photo (chantier_id)');
        $this->addSql('CREATE INDEX idx_photo_proprietaire ON photo (proprietaire_id)');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B7841876C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP CONSTRAINT FK_14B7841876C50E4A');
        $this->addSql('DROP TABLE photo');
    }
}
