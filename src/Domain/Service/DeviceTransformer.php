<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class DeviceTransformer
{
    public function transform(array $device): array
    {
        $location = $this->buildLocation($device);
        $customFields = $device['custom_fields'] ?? [];

        return [
            'found' => true,
            'netbox_id' => $device['id'] ?? null,
            'asset_id' => $device['asset_tag'] ?? '',
            'serial_number' => $device['serial'] ?? '',
            'manufacturer' => $this->extractName($device['device_type']['manufacturer'] ?? null),
            'model' => $this->extractDisplay($device['device_type'] ?? null),
            'device_type' => $device['device_type']['model'] ?? $this->extractDisplay($device['device_type'] ?? null),
            'location' => $location,
            'site_id' => isset($device['site']['id']) ? (string) $device['site']['id'] : '',
            'site_name' => $device['site']['name'] ?? '',
            'site_group_id' => isset($device['site']['group']['id']) ? (string) $device['site']['group']['id'] : '',
            'site_group_name' => $device['site']['group']['name'] ?? '',
            'region_id' => isset($device['site']['region']['id']) ? (string) $device['site']['region']['id'] : '',
            'region_name' => $device['site']['region']['name'] ?? '',
            'status' => $this->extractValue($device['status'] ?? null),
            'tenant_id' => isset($device['tenant']['id']) ? (string) $device['tenant']['id'] : '',
            'tenant_name' => $device['tenant']['name'] ?? '',
            'custom_fields' => [
                'asset_owner' => $customFields['cf_asset_owner'] ?? $customFields['asset_owner'] ?? '',
                'service' => $customFields['cf_service'] ?? $customFields['service'] ?? '',
                'criticality' => $customFields['cf_criticality'] ?? $customFields['criticality'] ?? '',
                'nutzungstyp' => $customFields['cf_nutzungstyp'] ?? $customFields['nutzungstyp'] ?? '',
                'admin_user' => $customFields['cf_admin_user'] ?? $customFields['admin_user'] ?? '',
                'security_owner' => $customFields['cf_security_owner'] ?? $customFields['security_owner'] ?? '',
            ],
        ];
    }

    private function buildLocation(array $device): string
    {
        $parts = [];
        if (!empty($device['site']['display'])) {
            $parts[] = $device['site']['display'];
        } elseif (!empty($device['site']['name'])) {
            $parts[] = $device['site']['name'];
        }
        if (!empty($device['location']['display'])) {
            $parts[] = $device['location']['display'];
        }
        if (!empty($device['rack']['display'])) {
            $rackStr = $device['rack']['display'];
            if (isset($device['position'])) {
                $rackStr .= ' / U' . $device['position'];
            }
            $parts[] = $rackStr;
        }
        return implode(' / ', $parts);
    }

    private function extractName(?array $obj): string
    {
        if ($obj === null) {
            return '';
        }
        return $obj['name'] ?? $obj['display'] ?? '';
    }

    private function extractDisplay(?array $obj): string
    {
        if ($obj === null) {
            return '';
        }
        return $obj['display'] ?? $obj['name'] ?? '';
    }

    private function extractValue(mixed $status): string
    {
        if (is_array($status)) {
            return $status['value'] ?? '';
        }
        if (is_string($status)) {
            return $status;
        }
        return '';
    }
}
