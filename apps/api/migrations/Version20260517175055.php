<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260517175055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_login_code (
              id UUID NOT NULL,
              code VARCHAR(64) NOT NULL,
              token_raw VARCHAR(512) NOT NULL,
              refresh_token_raw VARCHAR(255) NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              utilise_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              cree_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              auth_token_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_23A1146677153098 ON oauth_login_code (code)');
        $this->addSql('CREATE INDEX IDX_23A114666524603F ON oauth_login_code (auth_token_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              oauth_login_code
            ADD
              CONSTRAINT FK_23A114666524603F FOREIGN KEY (auth_token_id) REFERENCES auth_token (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE oauth_login_code DROP CONSTRAINT FK_23A114666524603F');
        $this->addSql('DROP TABLE oauth_login_code');
    }
}
