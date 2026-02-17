<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Mail\MailBuilder;
use PHPUnit\Framework\TestCase;

class MailBuilderTest extends TestCase
{
    private array $sampleEvent;

    protected function setUp(): void
    {
        $this->sampleEvent = [
            'track' => 'rz_assets',
            'label' => 'Inbetriebnahme RZ-Asset',
            'category' => 'RZ',
            'subject_type' => 'Inbetriebnahme',
            'required_fields' => ['asset_id', 'device_type'],
        ];
    }

    public function testBuildContainsEventLabel(): void
    {
        $body = MailBuilder::build($this->sampleEvent, ['asset_id' => 'SRV-001'], 'req-123');
        $this->assertStringContainsString('Inbetriebnahme RZ-Asset', $body);
    }

    public function testBuildContainsUppercaseLabel(): void
    {
        $body = MailBuilder::build($this->sampleEvent, ['asset_id' => 'SRV-001'], 'req-123');
        $this->assertStringContainsString('INBETRIEBNAHME RZ-ASSET', $body);
    }

    public function testBuildContainsCategory(): void
    {
        $body = MailBuilder::build($this->sampleEvent, ['asset_id' => 'SRV-001'], 'req-123');
        $this->assertStringContainsString('Kategorie:   RZ', $body);
    }

    public function testBuildContainsRequestId(): void
    {
        $requestId = 'abc-def-123-456';
        $body = MailBuilder::build($this->sampleEvent, ['asset_id' => 'SRV-001'], $requestId);
        $this->assertStringContainsString('Request-ID:  abc-def-123-456', $body);
        $this->assertStringContainsString('Request-ID: abc-def-123-456', $body);
    }

    public function testBuildContainsTimestamp(): void
    {
        $body = MailBuilder::build($this->sampleEvent, ['asset_id' => 'SRV-001'], 'req-123');
        $this->assertStringContainsString('Zeitstempel:', $body);
        // Should contain current date
        $this->assertStringContainsString(date('Y-m-d'), $body);
    }

    public function testBuildContainsFieldLabels(): void
    {
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'manufacturer' => 'Dell',
        ];
        $body = MailBuilder::build($this->sampleEvent, $data, 'req-123');
        $this->assertStringContainsString('Asset-ID:', $body);
        $this->assertStringContainsString('SRV-001', $body);
        $this->assertStringContainsString('Gerätetyp:', $body);
        $this->assertStringContainsString('Server', $body);
        $this->assertStringContainsString('Hersteller:', $body);
        $this->assertStringContainsString('Dell', $body);
    }

    public function testBuildBooleanTrueDisplaysJa(): void
    {
        $data = ['monitoring_active' => true];
        $body = MailBuilder::build($this->sampleEvent, $data, 'req-123');
        $this->assertStringContainsString('Ja', $body);
    }

    public function testBuildBooleanFalseDisplaysNein(): void
    {
        $data = ['monitoring_active' => false];
        $body = MailBuilder::build($this->sampleEvent, $data, 'req-123');
        $this->assertStringContainsString('Nein', $body);
    }

    public function testBuildUnknownFieldUsesKeyAsLabel(): void
    {
        $data = ['custom_field' => 'custom_value'];
        $body = MailBuilder::build($this->sampleEvent, $data, 'req-123');
        $this->assertStringContainsString('custom_field:', $body);
        $this->assertStringContainsString('custom_value', $body);
    }

    public function testBuildContainsC5EvidenceHeader(): void
    {
        $body = MailBuilder::build($this->sampleEvent, [], 'req-123');
        $this->assertStringContainsString('C5 EVIDENCE', $body);
    }

    public function testBuildContainsErfassteDatenSection(): void
    {
        $body = MailBuilder::build($this->sampleEvent, [], 'req-123');
        $this->assertStringContainsString('ERFASSTE DATEN', $body);
    }

    public function testBuildContainsFooter(): void
    {
        $body = MailBuilder::build($this->sampleEvent, [], 'req-123');
        $this->assertStringContainsString('Diese E-Mail wurde automatisch vom C5 Evidence Tool erstellt.', $body);
    }

    public function testBuildUsesCarriageReturnLineFeed(): void
    {
        $body = MailBuilder::build($this->sampleEvent, ['asset_id' => 'SRV-001'], 'req-123');
        $this->assertStringContainsString("\r\n", $body);
    }

    public function testBuildWithMultipleDataFields(): void
    {
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'manufacturer' => 'Dell',
            'model' => 'PowerEdge R740',
            'serial_number' => 'ABC123',
            'monitoring_active' => true,
            'patch_process' => true,
        ];
        $body = MailBuilder::build($this->sampleEvent, $data, 'req-123');

        $this->assertStringContainsString('SRV-001', $body);
        $this->assertStringContainsString('Server', $body);
        $this->assertStringContainsString('Dell', $body);
        $this->assertStringContainsString('PowerEdge R740', $body);
        $this->assertStringContainsString('ABC123', $body);
    }

    public function testBuildWithAdminEvent(): void
    {
        $adminEvent = [
            'track' => 'admin_devices',
            'label' => 'Rückgabe Admin-Endgerät',
            'category' => 'ADM',
            'subject_type' => 'Rückgabe',
            'required_fields' => ['asset_id'],
        ];
        $body = MailBuilder::build($adminEvent, ['asset_id' => 'WS-100'], 'req-456');
        $this->assertStringContainsString('Kategorie:   ADM', $body);
        $this->assertStringContainsString('Rückgabe Admin-Endgerät', $body);
    }
}
