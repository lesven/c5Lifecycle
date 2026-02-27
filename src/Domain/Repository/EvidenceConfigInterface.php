<?php

declare(strict_types=1);

namespace App\Domain\Repository;

/**
 * Configuration repository for C5 evidence settings.
 *
 * Provides access to evidence recipients, Jira rules, NetBox settings, and SMTP config.
 * Infrastructure implementations may load from YAML, environment, or database.
 */
interface EvidenceConfigInterface
{
    /**
     * @return array{to: string, cc: string[]}
     */
    public function getEvidenceRecipients(string $track): array;

    /** Get Jira rule for an event type: 'none', 'optional', 'required' */
    public function getJiraRule(string $eventType): string;

    public function isJiraEnabled(): bool;

    public function isNetBoxEnabled(): bool;

    /** Get NetBox sync rule: 'update_status', 'journal_only', 'none' */
    public function getNetBoxSyncRule(string $eventType): string;

    /** Get NetBox error handling mode: 'warn' or 'fail' */
    public function getNetBoxOnError(): string;

    public function isNetBoxDebug(): bool;

    public function getContactRoleOwner(): string;

    public function isCreateOnProvision(): bool;

    /** @return array{device_type_id: int, site_id: int, role_id: int} */
    public function getProvisionDefaults(): array;

    public function getSmtpFromAddress(): string;

    public function getSmtpFromName(): string;

    public function getJiraProjectKey(): string;

    public function getJiraIssueType(): string;
}
