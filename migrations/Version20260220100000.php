<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create submission_log table for audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE submission_log (
            id SERIAL PRIMARY KEY,
            request_id VARCHAR(36) NOT NULL UNIQUE,
            event_type VARCHAR(50) NOT NULL,
            asset_id VARCHAR(100) NOT NULL,
            data JSON NOT NULL,
            mail_sent BOOLEAN NOT NULL DEFAULT FALSE,
            jira_ticket VARCHAR(50) DEFAULT NULL,
            netbox_synced BOOLEAN NOT NULL DEFAULT FALSE,
            submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        $this->addSql('COMMENT ON COLUMN submission_log.submitted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_event_type ON submission_log (event_type)');
        $this->addSql('CREATE INDEX idx_asset_id ON submission_log (asset_id)');
        $this->addSql('CREATE INDEX idx_submitted_at ON submission_log (submitted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE submission_log');
    }
}
