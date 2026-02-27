<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table for authentication and authorization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            email VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            roles JSON NOT NULL DEFAULT \'["ROLE_USER"]\',
            display_name VARCHAR(100) NOT NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_email ON users (email)');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
