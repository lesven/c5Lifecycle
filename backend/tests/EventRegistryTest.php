<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\EventRegistry;
use PHPUnit\Framework\TestCase;

class EventRegistryTest extends TestCase
{
    /** @var string[] All known event types */
    private array $allEventTypes = [
        'rz_provision',
        'rz_retire',
        'rz_owner_confirm',
        'admin_provision',
        'admin_user_commitment',
        'admin_return',
        'admin_access_cleanup',
    ];

    public function testExistsReturnsTrueForAllKnownEvents(): void
    {
        foreach ($this->allEventTypes as $eventType) {
            $this->assertTrue(
                EventRegistry::exists($eventType),
                "Event '{$eventType}' should exist"
            );
        }
    }

    public function testExistsReturnsFalseForUnknownEvent(): void
    {
        $this->assertFalse(EventRegistry::exists('nonexistent'));
        $this->assertFalse(EventRegistry::exists(''));
    }

    public function testGetReturnsArrayForKnownEvent(): void
    {
        foreach ($this->allEventTypes as $eventType) {
            $event = EventRegistry::get($eventType);
            $this->assertIsArray($event, "Event '{$eventType}' should return array");
            $this->assertArrayHasKey('track', $event);
            $this->assertArrayHasKey('label', $event);
            $this->assertArrayHasKey('category', $event);
            $this->assertArrayHasKey('subject_type', $event);
            $this->assertArrayHasKey('required_fields', $event);
        }
    }

    public function testGetReturnsNullForUnknownEvent(): void
    {
        $this->assertNull(EventRegistry::get('nonexistent'));
    }

    public function testRzEventsHaveCorrectTrack(): void
    {
        $rzEvents = ['rz_provision', 'rz_retire', 'rz_owner_confirm'];
        foreach ($rzEvents as $eventType) {
            $event = EventRegistry::get($eventType);
            $this->assertEquals('rz_assets', $event['track'], "Event '{$eventType}' should have track 'rz_assets'");
            $this->assertEquals('RZ', $event['category'], "Event '{$eventType}' should have category 'RZ'");
        }
    }

    public function testAdminEventsHaveCorrectTrack(): void
    {
        $adminEvents = ['admin_provision', 'admin_user_commitment', 'admin_return', 'admin_access_cleanup'];
        foreach ($adminEvents as $eventType) {
            $event = EventRegistry::get($eventType);
            $this->assertEquals('admin_devices', $event['track'], "Event '{$eventType}' should have track 'admin_devices'");
            $this->assertEquals('ADM', $event['category'], "Event '{$eventType}' should have category 'ADM'");
        }
    }

    public function testAllEventsHaveAssetIdRequired(): void
    {
        foreach ($this->allEventTypes as $eventType) {
            $event = EventRegistry::get($eventType);
            $this->assertContains(
                'asset_id',
                $event['required_fields'],
                "Event '{$eventType}' should require 'asset_id'"
            );
        }
    }

    public function testRzProvisionRequiredFields(): void
    {
        $event = EventRegistry::get('rz_provision');
        $expected = [
            'asset_id', 'device_type', 'manufacturer', 'model', 'serial_number',
            'location', 'commission_date', 'asset_owner', 'service', 'criticality',
            'change_ref', 'monitoring_active', 'patch_process', 'access_controlled',
        ];
        $this->assertEquals($expected, $event['required_fields']);
    }

    public function testRzRetireRequiredFields(): void
    {
        $event = EventRegistry::get('rz_retire');
        $expected = [
            'asset_id', 'retire_date', 'reason', 'owner_approval', 'followup', 'data_handling',
        ];
        $this->assertEquals($expected, $event['required_fields']);
    }

    public function testBuildSubjectForRzProvision(): void
    {
        $subject = EventRegistry::buildSubject('rz_provision', 'SRV-001');
        $this->assertEquals('[C5 Evidence] RZ - Inbetriebnahme - SRV-001', $subject);
    }

    public function testBuildSubjectForRzRetire(): void
    {
        $subject = EventRegistry::buildSubject('rz_retire', 'SRV-002');
        $this->assertEquals('[C5 Evidence] RZ - Außerbetriebnahme - SRV-002', $subject);
    }

    public function testBuildSubjectForAdminProvision(): void
    {
        $subject = EventRegistry::buildSubject('admin_provision', 'WS-100');
        $this->assertEquals('[C5 Evidence] ADM - Inbetriebnahme - WS-100', $subject);
    }

    public function testBuildSubjectForAdminReturn(): void
    {
        $subject = EventRegistry::buildSubject('admin_return', 'WS-200');
        $this->assertEquals('[C5 Evidence] ADM - Rückgabe - WS-200', $subject);
    }

    public function testBuildSubjectForAdminAccessCleanup(): void
    {
        $subject = EventRegistry::buildSubject('admin_access_cleanup', 'WS-300');
        $this->assertEquals('[C5 Evidence] ADM - Access Cleanup - WS-300', $subject);
    }

    public function testBuildSubjectContainsC5EvidenceTag(): void
    {
        foreach ($this->allEventTypes as $eventType) {
            $subject = EventRegistry::buildSubject($eventType, 'TEST-001');
            $this->assertStringStartsWith('[C5 Evidence]', $subject);
            $this->assertStringEndsWith('TEST-001', $subject);
        }
    }

    public function testEventLabelsAreNonEmpty(): void
    {
        foreach ($this->allEventTypes as $eventType) {
            $event = EventRegistry::get($eventType);
            $this->assertNotEmpty($event['label'], "Event '{$eventType}' should have a non-empty label");
            $this->assertNotEmpty($event['subject_type'], "Event '{$eventType}' should have a non-empty subject_type");
        }
    }

    public function testSevenEventsExist(): void
    {
        $this->assertCount(7, $this->allEventTypes);
        foreach ($this->allEventTypes as $eventType) {
            $this->assertTrue(EventRegistry::exists($eventType));
        }
    }
}
