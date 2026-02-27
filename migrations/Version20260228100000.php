<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add submitted_by column and index to submission_log table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE submission_log ADD COLUMN submitted_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_submitted_by ON submission_log (submitted_by)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_submitted_by ON submission_log');
        $this->addSql('ALTER TABLE submission_log DROP COLUMN submitted_by');
    }
}
