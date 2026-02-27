<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\ValueObject\EventDefinition;
use App\Domain\ValueObject\EventType;

final class EventRegistry
{
    /** @var array<string, EventDefinition>|null Lazy-initialized definitions */
    private ?array $definitions = null;

    public function get(string $eventType): ?EventDefinition
    {
        return $this->getDefinitions()[$eventType] ?? null;
    }

    public function getByEnum(EventType $eventType): EventDefinition
    {
        return $this->getDefinitions()[$eventType->value];
    }

    public function exists(string $eventType): bool
    {
        return isset($this->getDefinitions()[$eventType]);
    }

    public function buildSubject(string $eventType, string $assetId): string
    {
        $event = $this->getDefinitions()[$eventType];

        return sprintf('[C5 Evidence] %s - %s - %s', $event->category, $event->subjectType, $assetId);
    }

    /** @return array<string, EventDefinition> */
    public function all(): array
    {
        return $this->getDefinitions();
    }

    /** @return array<string, EventDefinition> */
    private function getDefinitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $this->definitions = [
            'rz_provision' => new EventDefinition(
                track: 'rz_assets',
                label: 'Inbetriebnahme RZ-Asset',
                category: 'RZ',
                subjectType: 'Inbetriebnahme',
                requiredFields: [
                    'asset_id', 'device_type',
                    'location', 'commission_date', 'asset_owner', 'criticality',
                    'change_ref', 'monitoring_active', 'patch_process', 'access_controlled',
                ],
            ),
            'rz_retire' => new EventDefinition(
                track: 'rz_assets',
                label: 'Außerbetriebnahme RZ-Asset',
                category: 'RZ',
                subjectType: 'Außerbetriebnahme',
                requiredFields: [
                    'asset_id', 'retire_date', 'reason', 'owner_approval', 'followup', 'data_handling',
                ],
            ),
            'rz_owner_confirm' => new EventDefinition(
                track: 'rz_assets',
                label: 'Owner-Betriebsbestätigung',
                category: 'RZ',
                subjectType: 'Betriebsbestätigung',
                requiredFields: [
                    'asset_id', 'owner', 'confirm_date',
                    'purpose_bound', 'change_process', 'admin_access_controlled', 'lifecycle_managed',
                ],
            ),
            'admin_provision' => new EventDefinition(
                track: 'admin_devices',
                label: 'Inbetriebnahme Admin-Endgerät',
                category: 'ADM',
                subjectType: 'Inbetriebnahme',
                requiredFields: [
                    'asset_id', 'device_type',
                    'commission_date', 'admin_user', 'security_owner', 'purpose',
                    'disk_encryption', 'mfa_active', 'edr_active', 'patch_process', 'no_private_use',
                ],
            ),
            'admin_user_commitment' => new EventDefinition(
                track: 'admin_devices',
                label: 'Verpflichtung Admin-User',
                category: 'ADM',
                subjectType: 'Verpflichtung',
                requiredFields: [
                    'asset_id', 'admin_user', 'commitment_date',
                    'admin_tasks_only', 'no_mail_office', 'no_credential_sharing',
                    'report_loss', 'return_on_change',
                ],
            ),
            'admin_return' => new EventDefinition(
                track: 'admin_devices',
                label: 'Rückgabe Admin-Endgerät',
                category: 'ADM',
                subjectType: 'Rückgabe',
                requiredFields: [
                    'asset_id', 'admin_user', 'return_date', 'return_reason',
                    'condition', 'accessories_complete',
                ],
            ),
            'admin_access_cleanup' => new EventDefinition(
                track: 'admin_devices',
                label: 'Privileged Access Cleanup',
                category: 'ADM',
                subjectType: 'Access Cleanup',
                requiredFields: [
                    'asset_id', 'admin_user', 'cleanup_date',
                    'account_removed', 'keys_revoked',
                ],
            ),
        ];

        return $this->definitions;
    }
}
