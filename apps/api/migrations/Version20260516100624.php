<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516100624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE oauth_account (id UUID NOT NULL, provider VARCHAR(32) NOT NULL, provider_user_id VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6E30F9D1A76ED395 ON oauth_account (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_oauth_account_provider_user ON oauth_account (provider, provider_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_oauth_account_user_provider ON oauth_account (user_id, provider)');
        $this->addSql('ALTER TABLE oauth_account ADD CONSTRAINT FK_6E30F9D1A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE oauth_account DROP CONSTRAINT FK_6E30F9D1A76ED395');
        $this->addSql('DROP TABLE oauth_account');
    }
}
