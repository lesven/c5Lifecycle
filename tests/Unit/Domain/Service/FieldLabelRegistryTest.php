<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Service\FieldLabelRegistry;
use PHPUnit\Framework\TestCase;

class FieldLabelRegistryTest extends TestCase
{
    private FieldLabelRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new FieldLabelRegistry();
    }

    public function testAllReturnsNonEmptyArray(): void
    {
        $labels = $this->registry->all();
        $this->assertNotEmpty($labels);
        $this->assertIsArray($labels);
    }

    public function testGetReturnsLabelForKnownField(): void
    {
        $this->assertSame('Asset-ID', $this->registry->get('asset_id'));
        $this->assertSame('Gerätetyp', $this->registry->get('device_type'));
        $this->assertSame('Hersteller', $this->registry->get('manufacturer'));
    }

    public function testGetReturnsFallbackForUnknownField(): void
    {
        $this->assertSame('unknown_field', $this->registry->get('unknown_field'));
        $this->assertSame('custom_field_xyz', $this->registry->get('custom_field_xyz'));
    }

    public function testAllContainsAllExpectedFields(): void
    {
        $labels = $this->registry->all();
        $expectedFields = [
            'asset_id', 'device_type', 'manufacturer', 'model', 'serial_number',
            'commission_date', 'asset_owner', 'criticality', 'monitoring_active',
            'retire_date', 'reason', 'data_handling', 'admin_user', 'security_owner',
            'return_date', 'cleanup_date', 'ticket_ref', 'tenant_name',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $labels, "Missing label for field: {$field}");
        }
    }

    public function testLabelsAreGermanStrings(): void
    {
        $labels = $this->registry->all();
        foreach ($labels as $key => $value) {
            $this->assertIsString($value, "Label for {$key} should be string");
            $this->assertNotEmpty($value, "Label for {$key} should not be empty");
        }
    }

    public function testRzOwnerConfirmFieldLabels(): void
    {
        // Test updated labels for rz_owner_confirm
        $this->assertSame('Admin-Zugriffe kontrolliert', $this->registry->get('admin_access_controlled'));
        $this->assertSame('Wartungsfenster okay', $this->registry->get('maintenance_window_ok'));
        $this->assertSame('Zweckgebundener Betrieb', $this->registry->get('purpose_bound'));
    }

    public function testRemovedFieldsNotInRegistry(): void
    {
        // These fields were removed from FieldLabelRegistry
        $labels = $this->registry->all();
        $this->assertFalse(isset($labels['change_process']), 'change_process should be removed from registry');
        $this->assertFalse(isset($labels['lifecycle_managed']), 'lifecycle_managed should be removed from registry');
    }
}
