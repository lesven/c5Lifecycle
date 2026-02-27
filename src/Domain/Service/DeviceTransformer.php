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
            'device_type' => $this->extractDisplay($device['device_role'] ?? null),
            'location' => $location,
            'status' => $this->extractValue($device['status'] ?? null),
            'tenant_id' => isset($device['tenant']['id']) ? (string) $device['tenant']['id'] : '',
            'tenant_name' => $device['tenant']['name'] ?? '',
            'custom_fields' => [
                'asset_owner' => $customFields['cf_asset_owner'] ?? $customFields['asset_owner'] ?? '',
                'service' => $customFields['cf_service'] ?? $customFields['service'] ?? '',
                'criticality' => $customFields['cf_criticality'] ?? $customFields['criticality'] ?? '',
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
