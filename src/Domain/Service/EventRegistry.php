<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\ValueObject\EventType;

final class EventRegistry
{
    /** @var array<string, array{track: string, label: string, category: string, subject_type: string, required_fields: string[]}> */
    private const EVENTS = [
        'rz_provision' => [
            'track' => 'rz_assets',
            'label' => 'Inbetriebnahme RZ-Asset',
            'category' => 'RZ',
            'subject_type' => 'Inbetriebnahme',
            'required_fields' => [
                'asset_id', 'device_type', 'manufacturer', 'model', 'serial_number',
                'location', 'commission_date', 'asset_owner', 'service', 'criticality',
                'change_ref', 'monitoring_active', 'patch_process', 'access_controlled',
            ],
        ],
        'rz_retire' => [
            'track' => 'rz_assets',
            'label' => 'Außerbetriebnahme RZ-Asset',
            'category' => 'RZ',
            'subject_type' => 'Außerbetriebnahme',
            'required_fields' => [
                'asset_id', 'retire_date', 'reason', 'owner_approval', 'followup', 'data_handling',
            ],
        ],
        'rz_owner_confirm' => [
            'track' => 'rz_assets',
            'label' => 'Owner-Betriebsbestätigung',
            'category' => 'RZ',
            'subject_type' => 'Betriebsbestätigung',
            'required_fields' => [
                'asset_id', 'owner', 'confirm_date',
                'purpose_bound', 'change_process', 'admin_access_controlled', 'lifecycle_managed',
            ],
        ],
        'admin_provision' => [
            'track' => 'admin_devices',
            'label' => 'Inbetriebnahme Admin-Endgerät',
            'category' => 'ADM',
            'subject_type' => 'Inbetriebnahme',
            'required_fields' => [
                'asset_id', 'device_type', 'manufacturer', 'model', 'serial_number',
                'commission_date', 'admin_user', 'security_owner', 'purpose',
                'disk_encryption', 'mfa_active', 'edr_active', 'patch_process', 'no_private_use',
            ],
        ],
        'admin_user_commitment' => [
            'track' => 'admin_devices',
            'label' => 'Verpflichtung Admin-User',
            'category' => 'ADM',
            'subject_type' => 'Verpflichtung',
            'required_fields' => [
                'asset_id', 'admin_user', 'commitment_date',
                'admin_tasks_only', 'no_mail_office', 'no_credential_sharing',
                'report_loss', 'return_on_change',
            ],
        ],
        'admin_return' => [
            'track' => 'admin_devices',
            'label' => 'Rückgabe Admin-Endgerät',
            'category' => 'ADM',
            'subject_type' => 'Rückgabe',
            'required_fields' => [
                'asset_id', 'admin_user', 'return_date', 'return_reason',
                'condition', 'accessories_complete',
            ],
        ],
        'admin_access_cleanup' => [
            'track' => 'admin_devices',
            'label' => 'Privileged Access Cleanup',
            'category' => 'ADM',
            'subject_type' => 'Access Cleanup',
            'required_fields' => [
                'asset_id', 'admin_user', 'cleanup_date',
                'account_removed', 'keys_revoked',
            ],
        ],
    ];

    public function get(string $eventType): ?array
    {
        return self::EVENTS[$eventType] ?? null;
    }

    public function getByEnum(EventType $eventType): array
    {
        return self::EVENTS[$eventType->value];
    }

    public function exists(string $eventType): bool
    {
        return isset(self::EVENTS[$eventType]);
    }

    public function buildSubject(string $eventType, string $assetId): string
    {
        $event = self::EVENTS[$eventType];
        return sprintf('[C5 Evidence] %s - %s - %s', $event['category'], $event['subject_type'], $assetId);
    }

    /** @return array<string, array{track: string, label: string, category: string, subject_type: string, required_fields: string[]}> */
    public function all(): array
    {
        return self::EVENTS;
    }
}
