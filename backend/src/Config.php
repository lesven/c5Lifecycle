<?php
declare(strict_types=1);

namespace C5;

use Symfony\Component\Yaml\Yaml;

class Config
{
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function load(string $path): self
    {
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid config file format');
        }
        return new self($data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
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

    /** Get evidence email recipients for a track ('rz_assets' or 'admin_devices') */
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

    /** Check if NetBox integration is enabled */
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
}
