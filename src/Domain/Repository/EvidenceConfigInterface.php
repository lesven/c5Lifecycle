<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\ValueObject\JiraRule;
use App\Domain\ValueObject\NetBoxErrorMode;
use App\Domain\ValueObject\NetBoxSyncRule;

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

    public function getJiraRule(string $eventType): JiraRule;

    public function isJiraEnabled(): bool;

    public function isNetBoxEnabled(): bool;

    public function getNetBoxSyncRule(string $eventType): NetBoxSyncRule;

    public function getNetBoxOnError(): NetBoxErrorMode;

    public function isNetBoxDebug(): bool;

    public function getContactRoleOwner(): string;

    public function isCreateOnProvision(): bool;

    /** @return array{device_type_id: int, site_id: int, role_id: int} */
    public function getProvisionDefaults(): array;

    public function getSmtpFromAddress(): string;

    public function getSmtpFromName(): string;

    public function getJiraProjectKey(): string;

    public function getJiraIssueType(): string;

    /** Base URL of this application, e.g. https://c5.example.com (no trailing slash) */
    public function getAppBaseUrl(): string;

    /** Base URL of the NetBox instance, e.g. https://netbox.example.com (no trailing slash) */
    public function getNetBoxBaseUrl(): string;
}
