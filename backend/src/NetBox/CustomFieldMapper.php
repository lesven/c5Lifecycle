<?php
declare(strict_types=1);

namespace C5\NetBox;

class CustomFieldMapper
{
    /**
     * Custom fields to sync per event type.
     * Maps C5 form field names to NetBox custom field names (cf_ prefix).
     */
    private static array $fieldMap = [
        'rz_provision' => [
            'asset_owner'      => 'cf_asset_owner',
            'service'          => 'cf_service',
            'criticality'      => 'cf_criticality',
            'commission_date'  => 'cf_commission_date',
            'monitoring_active' => 'cf_monitoring_active',
            'patch_process'    => 'cf_patch_process',
            'access_controlled' => 'cf_access_controlled',
            'change_ref'       => 'cf_change_ref',
        ],
        'rz_retire' => [
            'retire_date'       => 'cf_retire_date',
            'reason'            => 'cf_retire_reason',
            'data_handling'     => 'cf_data_handling',
            'data_handling_ref' => 'cf_data_handling_ref',
            'followup'          => 'cf_followup',
        ],
        'admin_provision' => [
            'admin_user'       => 'cf_admin_user',
            'security_owner'   => 'cf_security_owner',
            'purpose'          => 'cf_purpose',
            'disk_encryption'  => 'cf_disk_encryption',
            'mfa_active'       => 'cf_mfa_active',
            'edr_active'       => 'cf_edr_active',
            'no_private_use'   => 'cf_no_private_use',
        ],
    ];

    /**
     * Get custom_fields data for a NetBox PATCH request based on the event type and form data.
     * Returns an associative array of cf_ fields, or empty array if no mapping exists.
     */
    public static function map(string $eventType, array $data): array
    {
        $mapping = self::$fieldMap[$eventType] ?? [];
        if (empty($mapping)) {
            return [];
        }

        $customFields = [];
        foreach ($mapping as $formField => $cfField) {
            if (array_key_exists($formField, $data)) {
                $customFields[$cfField] = $data[$formField];
            }
        }

        return $customFields;
    }

    /**
     * Check if an event type has custom field mappings.
     */
    public static function hasMapping(string $eventType): bool
    {
        return isset(self::$fieldMap[$eventType]) && !empty(self::$fieldMap[$eventType]);
    }
}
