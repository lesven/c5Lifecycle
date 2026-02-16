<?php
declare(strict_types=1);

namespace C5;

class EventRegistry
{
    /** @var array<string, array{track: string, label: string, subject_prefix: string, required_fields: string[]}> */
    private static array $events = [
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
            // data_handling_ref is conditionally required (handled in handler)
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
            // ticket_ref is conditionally required (handled in handler)
        ],
    ];

    public static function get(string $eventType): ?array
    {
        return self::$events[$eventType] ?? null;
    }

    public static function exists(string $eventType): bool
    {
        return isset(self::$events[$eventType]);
    }

    public static function buildSubject(string $eventType, string $assetId): string
    {
        $event = self::$events[$eventType];
        return sprintf('[C5 Evidence] %s - %s - %s', $event['category'], $event['subject_type'], $assetId);
    }
}
