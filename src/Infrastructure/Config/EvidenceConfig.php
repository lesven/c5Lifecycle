<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use Symfony\Component\Yaml\Yaml;
use RuntimeException;

final readonly class EvidenceConfig
{
    private function __construct(
        private array $data,
    ) {}

    public static function fromYamlFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Config file not found: {$path}");
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid config file format');
        }

        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /** Get evidence email recipients for a track ('rz_assets' or 'admin_devices')
     *
     * @return array{to: string, cc: string[]}
     */
    public function getEvidenceRecipients(string $track): array
    {
        return [
            'to' => $this->get("evidence.{$track}.to", ''),
            'cc' => $this->get("evidence.{$track}.cc", []),
        ];
    }

    /** Get Jira rule for an event type: 'none', 'optional', 'required' */
    public function getJiraRule(string $eventType): string
    {
        if (!$this->get('jira.enabled', false)) {
            return 'none';
        }
        return $this->get("jira_rules.{$eventType}", 'none');
    }

    public function isJiraEnabled(): bool
    {
        return (bool) $this->get('jira.enabled', false);
    }

    public function isNetBoxEnabled(): bool
    {
        return (bool) $this->get('netbox.enabled', false);
    }

    /** Get NetBox sync rule for an event type: 'update_status', 'journal_only', 'none' */
    public function getNetBoxSyncRule(string $eventType): string
    {
        if (!$this->isNetBoxEnabled()) {
            return 'none';
        }
        return $this->get("netbox.sync_rules.{$eventType}", 'none');
    }

    /** Get NetBox error handling mode: 'warn' or 'fail' */
    public function getNetBoxOnError(): string
    {
        return $this->get('netbox.on_error', 'warn');
    }

    public function isNetBoxDebug(): bool
    {
        return (bool) $this->get('netbox.debug', false);
    }

    public function getContactRoleOwner(): string
    {
        return trim((string) $this->get('netbox.contact_role_owner', ''));
    }

    public function isCreateOnProvision(): bool
    {
        return (bool) $this->get('netbox.create_on_provision', false);
    }

    /** @return array{device_type_id: int, site_id: int, role_id: int} */
    public function getProvisionDefaults(): array
    {
        return [
            'device_type_id' => (int) $this->get('netbox.provision_defaults.device_type_id', 0),
            'site_id' => (int) $this->get('netbox.provision_defaults.site_id', 0),
            'role_id' => (int) $this->get('netbox.provision_defaults.role_id', 0),
        ];
    }

    public function getSmtpFromAddress(): string
    {
        return $this->get('smtp.from_address', 'c5-evidence@localhost');
    }

    public function getSmtpFromName(): string
    {
        return $this->get('smtp.from_name', 'C5 Evidence Tool');
    }

    public function getJiraProjectKey(): string
    {
        return $this->get('jira.project_key', 'ITOPS');
    }

    public function getJiraIssueType(): string
    {
        return $this->get('jira.issue_type', 'Task');
    }
}
