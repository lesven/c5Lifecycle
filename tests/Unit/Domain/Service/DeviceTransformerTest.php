<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Service\DeviceTransformer;
use PHPUnit\Framework\TestCase;

class DeviceTransformerTest extends TestCase
{
    private DeviceTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new DeviceTransformer();
    }

    private function sampleDevice(): array
    {
        return [
            'id' => 42,
            'asset_tag' => 'SRV-2024-001',
            'serial' => 'ABC123XYZ',
            'status' => ['value' => 'active', 'label' => 'Active'],
            'device_type' => [
                'display' => 'PowerEdge R750',
                'manufacturer' => ['name' => 'Dell', 'display' => 'Dell'],
            ],
            'device_role' => ['display' => 'Server', 'name' => 'server'],
            'site' => ['display' => 'RZ-Nord', 'name' => 'RZ-Nord'],
            'location' => ['display' => 'Halle A'],
            'rack' => ['display' => 'Rack A3'],
            'position' => 22,
            'custom_fields' => [
                'cf_asset_owner' => 'Team Platform',
                'cf_service' => 'Kubernetes Cluster',
                'cf_criticality' => 'hoch',
                'cf_admin_user' => 'max.mustermann@company.de',
                'cf_security_owner' => 'IT-Security Team',
            ],
        ];
    }

    public function testTransformReturnsFoundTrue(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertTrue($result['found']);
    }

    public function testTransformReturnsNetboxId(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals(42, $result['netbox_id']);
    }

    public function testTransformReturnsAssetId(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals('SRV-2024-001', $result['asset_id']);
    }

    public function testTransformReturnsSerialNumber(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals('ABC123XYZ', $result['serial_number']);
    }

    public function testTransformReturnsManufacturer(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals('Dell', $result['manufacturer']);
    }

    public function testTransformReturnsModel(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals('PowerEdge R750', $result['model']);
    }

    public function testTransformReturnsDeviceType(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals('Server', $result['device_type']);
    }

    public function testTransformReturnsStatus(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals('active', $result['status']);
    }

    public function testTransformBuildsLocationWithAllParts(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals('RZ-Nord / Halle A / Rack A3 / U22', $result['location']);
    }

    public function testTransformBuildsLocationWithoutRack(): void
    {
        $device = $this->sampleDevice();
        unset($device['rack'], $device['position']);
        $result = $this->transformer->transform($device);
        $this->assertEquals('RZ-Nord / Halle A', $result['location']);
    }

    public function testTransformBuildsLocationWithoutLocation(): void
    {
        $device = $this->sampleDevice();
        unset($device['location']);
        $result = $this->transformer->transform($device);
        $this->assertEquals('RZ-Nord / Rack A3 / U22', $result['location']);
    }

    public function testTransformReturnsCustomFields(): void
    {
        $result = $this->transformer->transform($this->sampleDevice());
        $this->assertEquals('Team Platform', $result['custom_fields']['asset_owner']);
        $this->assertEquals('Kubernetes Cluster', $result['custom_fields']['service']);
        $this->assertEquals('hoch', $result['custom_fields']['criticality']);
        $this->assertEquals('max.mustermann@company.de', $result['custom_fields']['admin_user']);
        $this->assertEquals('IT-Security Team', $result['custom_fields']['security_owner']);
    }

    public function testTransformHandlesEmptyCustomFields(): void
    {
        $device = $this->sampleDevice();
        $device['custom_fields'] = [];
        $result = $this->transformer->transform($device);
        $this->assertEquals('', $result['custom_fields']['asset_owner']);
        $this->assertEquals('', $result['custom_fields']['service']);
    }

    public function testTransformHandlesStatusAsString(): void
    {
        $device = $this->sampleDevice();
        $device['status'] = 'active';
        $result = $this->transformer->transform($device);
        $this->assertEquals('active', $result['status']);
    }

    public function testTransformHandlesMissingOptionalFields(): void
    {
        $device = [
            'id' => 1,
            'asset_tag' => 'TEST-001',
            'serial' => '',
            'status' => null,
            'device_type' => null,
            'device_role' => null,
        ];
        $result = $this->transformer->transform($device);
        $this->assertTrue($result['found']);
        $this->assertEquals(1, $result['netbox_id']);
        $this->assertEquals('TEST-001', $result['asset_id']);
        $this->assertEquals('', $result['manufacturer']);
        $this->assertEquals('', $result['model']);
        $this->assertEquals('', $result['device_type']);
        $this->assertEquals('', $result['status']);
    }

    public function testTransformWithAlternativeCustomFieldNames(): void
    {
        $device = $this->sampleDevice();
        $device['custom_fields'] = [
            'asset_owner' => 'Team X',
            'service' => 'Service Y',
        ];
        $result = $this->transformer->transform($device);
        $this->assertEquals('Team X', $result['custom_fields']['asset_owner']);
        $this->assertEquals('Service Y', $result['custom_fields']['service']);
    }
}
