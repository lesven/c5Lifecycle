<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\NetBox\CustomFieldMapper;
use PHPUnit\Framework\TestCase;

class CustomFieldMapperTest extends TestCase
{
    public function testRzProvisionMapsAllFields(): void
    {
        $data = [
            'asset_owner' => 'Team Platform',
            'service' => 'Kubernetes',
            'criticality' => 'hoch',
            'commission_date' => '2024-01-15',
            'monitoring_active' => true,
            'patch_process' => true,
            'access_controlled' => true,
            'change_ref' => 'CHG-001',
        ];
        $result = CustomFieldMapper::map('rz_provision', $data);
        $this->assertEquals('Team Platform', $result['cf_asset_owner']);
        $this->assertEquals('Kubernetes', $result['cf_service']);
        $this->assertEquals('hoch', $result['cf_criticality']);
        $this->assertEquals('2024-01-15', $result['cf_commission_date']);
        $this->assertTrue($result['cf_monitoring_active']);
        $this->assertTrue($result['cf_patch_process']);
        $this->assertTrue($result['cf_access_controlled']);
        $this->assertEquals('CHG-001', $result['cf_change_ref']);
    }

    public function testRzRetireMapsFields(): void
    {
        $data = [
            'retire_date' => '2024-06-01',
            'reason' => 'EOL',
            'data_handling' => 'Secure Wipe',
            'data_handling_ref' => 'WIPE-001',
            'followup' => 'Entsorgung',
        ];
        $result = CustomFieldMapper::map('rz_retire', $data);
        $this->assertEquals('2024-06-01', $result['cf_retire_date']);
        $this->assertEquals('EOL', $result['cf_retire_reason']);
        $this->assertEquals('Secure Wipe', $result['cf_data_handling']);
        $this->assertEquals('WIPE-001', $result['cf_data_handling_ref']);
        $this->assertEquals('Entsorgung', $result['cf_followup']);
    }

    public function testAdminProvisionMapsFields(): void
    {
        $data = [
            'admin_user' => 'admin1',
            'security_owner' => 'IT Security',
            'purpose' => 'Admin Access',
            'disk_encryption' => true,
            'mfa_active' => true,
            'edr_active' => true,
            'no_private_use' => true,
        ];
        $result = CustomFieldMapper::map('admin_provision', $data);
        $this->assertEquals('admin1', $result['cf_admin_user']);
        $this->assertEquals('IT Security', $result['cf_security_owner']);
        $this->assertEquals('Admin Access', $result['cf_purpose']);
        $this->assertTrue($result['cf_disk_encryption']);
        $this->assertTrue($result['cf_mfa_active']);
        $this->assertTrue($result['cf_edr_active']);
        $this->assertTrue($result['cf_no_private_use']);
    }

    public function testUnmappedEventReturnsEmptyArray(): void
    {
        $this->assertEmpty(CustomFieldMapper::map('admin_return', ['admin_user' => 'admin1']));
        $this->assertEmpty(CustomFieldMapper::map('rz_owner_confirm', ['owner' => 'Team X']));
    }

    public function testOnlyMapsExistingDataFields(): void
    {
        $data = ['asset_owner' => 'Team X'];
        $result = CustomFieldMapper::map('rz_provision', $data);
        $this->assertEquals('Team X', $result['cf_asset_owner']);
        $this->assertArrayNotHasKey('cf_service', $result);
        $this->assertArrayNotHasKey('cf_criticality', $result);
    }

    public function testHasMappingReturnsTrueForMappedEvents(): void
    {
        $this->assertTrue(CustomFieldMapper::hasMapping('rz_provision'));
        $this->assertTrue(CustomFieldMapper::hasMapping('rz_retire'));
        $this->assertTrue(CustomFieldMapper::hasMapping('admin_provision'));
    }

    public function testHasMappingReturnsFalseForUnmappedEvents(): void
    {
        $this->assertFalse(CustomFieldMapper::hasMapping('admin_return'));
        $this->assertFalse(CustomFieldMapper::hasMapping('rz_owner_confirm'));
        $this->assertFalse(CustomFieldMapper::hasMapping('unknown'));
    }
}
