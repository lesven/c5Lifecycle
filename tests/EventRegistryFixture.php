<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Service\EventRegistry;
use App\Domain\ValueObject\EventDefinition;

/**
 * Helper to create an EventRegistry with the standard 7 event definitions for tests.
 */
final class EventRegistryFixture
{
    public static function create(): EventRegistry
    {
        return new EventRegistry(self::definitions());
    }

    /**
     * @return array<string, EventDefinition>
     */
    public static function definitions(): array
    {
        return [
            'rz_provision' => new EventDefinition(
                track: 'rz_assets',
                label: 'Inbetriebnahme RZ-Asset',
                category: 'RZ',
                subjectType: 'Inbetriebnahme',
                requiredFields: [
                    'asset_id', 'device_type',
                    'region_id', 'site_group_id', 'site_id',
                    'commission_date', 'asset_owner', 'criticality',
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
                conditionalRules: [
                    'data_handling_ref' => [
                        'when' => ['field' => 'data_handling', 'operator' => 'not_in', 'value' => ['', 'Nicht relevant']],
                        'then' => 'Pflichtfeld (Data Handling ≠ Nicht relevant)',
                    ],
                ],
            ),
            'rz_owner_confirm' => new EventDefinition(
                track: 'rz_assets',
                label: 'Owner-Betriebsbestätigung',
                category: 'RZ',
                subjectType: 'Betriebsbestätigung',
                requiredFields: [
                    'asset_id', 'owner', 'confirm_date',
                    'purpose_bound', 'admin_access_controlled', 'maintenance_window_ok',
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
                conditionalRules: [
                    'ticket_ref' => [
                        'when' => ['field' => 'device_wiped', 'operator' => 'empty', 'value' => null],
                        'then' => 'Pflichtfeld (Wipe nicht abgeschlossen)',
                    ],
                ],
            ),
        ];
    }
}
