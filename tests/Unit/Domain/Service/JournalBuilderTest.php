<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Service\FieldLabelRegistry;
use App\Domain\Service\JournalBuilder;
use App\Domain\ValueObject\EventDefinition;
use PHPUnit\Framework\TestCase;

class JournalBuilderTest extends TestCase
{
    private JournalBuilder $builder;
    private EventDefinition $eventMeta;

    protected function setUp(): void
    {
        $this->builder = new JournalBuilder(new FieldLabelRegistry());
        $this->eventMeta = new EventDefinition(
            track: 'rz_assets',
            label: 'Inbetriebnahme RZ-Asset',
            category: 'RZ',
            subjectType: 'Inbetriebnahme',
            requiredFields: [],
        );
    }

    public function testBuildContainsEventLabel(): void
    {
        $result = $this->builder->build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'test@example.com');
        $this->assertStringContainsString('C5 Evidence: Inbetriebnahme RZ-Asset', $result);
    }

    public function testBuildContainsRequestId(): void
    {
        $result = $this->builder->build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'test@example.com');
        $this->assertStringContainsString('Request-ID: req-123', $result);
    }

    public function testBuildContainsAssetId(): void
    {
        $result = $this->builder->build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'test@example.com');
        $this->assertStringContainsString('Asset-ID: SRV-001', $result);
    }

    public function testBuildContainsDate(): void
    {
        $result = $this->builder->build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'test@example.com');
        $this->assertStringContainsString('Datum: ' . date('Y-m-d'), $result);
    }

    public function testBuildContainsEvidenceRecipient(): void
    {
        $result = $this->builder->build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'rz-evidence@company.de');
        $this->assertStringContainsString('Evidence-Mail versendet an: rz-evidence@company.de', $result);
    }

    public function testBuildContainsAssetOwnerAsSubmitter(): void
    {
        $data = ['asset_id' => 'SRV-001', 'asset_owner' => 'Team Platform'];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com');
        $this->assertStringContainsString('Erfasst von: Team Platform', $result);
    }

    public function testBuildContainsAdminUserAsSubmitter(): void
    {
        $data = ['asset_id' => 'WS-001', 'admin_user' => 'admin1'];
        $adminMeta = new EventDefinition(
            track: 'admin_devices',
            label: 'Inbetriebnahme Admin-Endgerät',
            category: 'ADM',
            subjectType: 'Inbetriebnahme',
            requiredFields: [],
        );
        $result = $this->builder->build('admin_provision', $adminMeta, $data, 'req-456', 'test@example.com');
        $this->assertStringContainsString('Erfasst von: admin1', $result);
    }

    public function testBuildRzRetireIncludesDataHandling(): void
    {
        $data = [
            'asset_id' => 'SRV-001',
            'data_handling' => 'Secure Wipe',
            'data_handling_ref' => 'WIPE-2024-001',
        ];
        $retireMeta = new EventDefinition(
            track: 'rz_assets',
            label: 'Außerbetriebnahme RZ-Asset',
            category: 'RZ',
            subjectType: 'Außerbetriebnahme',
            requiredFields: [],
        );
        $result = $this->builder->build('rz_retire', $retireMeta, $data, 'req-789', 'test@example.com');
        $this->assertStringContainsString('Data-Handling-Methode: Secure Wipe', $result);
        $this->assertStringContainsString('Nachweisreferenz: WIPE-2024-001', $result);
    }

    public function testBuildNonRetireEventDoesNotIncludeDataHandling(): void
    {
        $data = ['asset_id' => 'SRV-001', 'data_handling' => 'Secure Wipe'];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com');
        $this->assertStringNotContainsString('Data-Handling-Methode', $result);
    }

    public function testBuildHandlesMissingAssetId(): void
    {
        $result = $this->builder->build('rz_provision', $this->eventMeta, [], 'req-123', 'test@example.com');
        $this->assertStringContainsString('Asset-ID: UNKNOWN', $result);
    }

    public function testBuildWithSubmittedByIncludesSystemUserLine(): void
    {
        $data = ['asset_id' => 'SRV-001'];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com', 'Max Mustermann (max@company.de)');
        $this->assertStringContainsString('System-User: Max Mustermann (max@company.de)', $result);
    }

    public function testBuildWithoutSubmittedByBackwardsCompatible(): void
    {
        $data = ['asset_id' => 'SRV-001'];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com');
        $this->assertStringNotContainsString('System-User:', $result);
    }

    public function testBuildIncludesChangeRefWhenPresent(): void
    {
        $data = ['asset_id' => 'SRV-001', 'change_ref' => 'CHG-9999'];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com');
        $this->assertStringContainsString('Change-Ref: CHG-9999', $result);
    }

    public function testBuildDoesNotIncludeChangeRefWhenMissing(): void
    {
        $data = ['asset_id' => 'SRV-001'];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com');
        $this->assertStringNotContainsString('Change-Ref:', $result);
    }

    public function testBuildWithReProvisionContextAddsReProvisionLabel(): void
    {
        $data = ['asset_id' => 'SRV-001'];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com', null, ['is_re_provision' => true]);
        $this->assertStringContainsString('(Re-Provision)', $result);
    }

    public function testBuildWithoutReProvisionContextHasNoReProvisionLabel(): void
    {
        $data = ['asset_id' => 'SRV-001'];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com');
        $this->assertStringNotContainsString('Re-Provision', $result);
    }

    public function testBuildRzOwnerConfirmIncludesCheckboxConfirmations(): void
    {
        $ownerConfirmMeta = new EventDefinition(
            track: 'rz_assets',
            label: 'Owner-Betriebsbestätigung',
            category: 'RZ',
            subjectType: 'Betriebsbestätigung',
            requiredFields: [],
        );
        $data = [
            'asset_id' => 'SRV-001',
            'owner' => 'Team Infrastructure',
            'purpose_bound' => true,
            'admin_access_controlled' => true,
            'maintenance_window_ok' => false,
        ];
        $result = $this->builder->build('rz_owner_confirm', $ownerConfirmMeta, $data, 'req-999', 'test@example.com');

        // Should include confirmations section
        $this->assertStringContainsString('Bestätigungen:', $result);
        $this->assertStringContainsString('Zweckgebundener Betrieb: Ja', $result);
        $this->assertStringContainsString('Admin-Zugriffe kontrolliert: Ja', $result);
        $this->assertStringContainsString('Wartungsfenster okay: Nein', $result);
    }

    public function testBuildRzOwnerConfirmAllCheckboxesFalse(): void
    {
        $ownerConfirmMeta = new EventDefinition(
            track: 'rz_assets',
            label: 'Owner-Betriebsbestätigung',
            category: 'RZ',
            subjectType: 'Betriebsbestätigung',
            requiredFields: [],
        );
        $data = [
            'asset_id' => 'SRV-002',
            'owner' => 'Team X',
            'purpose_bound' => false,
            'admin_access_controlled' => false,
            'maintenance_window_ok' => false,
        ];
        $result = $this->builder->build('rz_owner_confirm', $ownerConfirmMeta, $data, 'req-888', 'test@example.com');

        $this->assertStringContainsString('Zweckgebundener Betrieb: Nein', $result);
        $this->assertStringContainsString('Admin-Zugriffe kontrolliert: Nein', $result);
        $this->assertStringContainsString('Wartungsfenster okay: Nein', $result);
    }

    public function testBuildRzOwnerConfirmMissingCheckboxesTreatedAsNein(): void
    {
        $ownerConfirmMeta = new EventDefinition(
            track: 'rz_assets',
            label: 'Owner-Betriebsbestätigung',
            category: 'RZ',
            subjectType: 'Betriebsbestätigung',
            requiredFields: [],
        );
        $data = [
            'asset_id' => 'SRV-003',
            'owner' => 'Team Y',
            // Missing checkbox fields
        ];
        $result = $this->builder->build('rz_owner_confirm', $ownerConfirmMeta, $data, 'req-777', 'test@example.com');

        // All should be Nein if missing
        $this->assertStringContainsString('Zweckgebundener Betrieb: Nein', $result);
        $this->assertStringContainsString('Admin-Zugriffe kontrolliert: Nein', $result);
        $this->assertStringContainsString('Wartungsfenster okay: Nein', $result);
    }

    public function testBuildNonOwnerConfirmEventDoesNotIncludeCheckboxes(): void
    {
        $data = [
            'asset_id' => 'SRV-004',
            'purpose_bound' => true,
            'admin_access_controlled' => true,
        ];
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-666', 'test@example.com');
        $this->assertStringNotContainsString('Bestätigungen:', $result);
    }

    // ============ FormFieldsSummary Tests ============

    public function testBuildRzProvisionWithFormFieldsSummaryIfNetBoxLookupsProvided(): void
    {
        $data = [
            'asset_id' => 'SRV-042',
            'device_type_id' => 1,
            'region_id' => 5,
            'site_group_id' => 10,
            'site_id' => 20,
            'commission_date' => '2026-03-03',
            'contact_id' => 42,
            'criticality' => 'hoch',
            'change_ref' => 'CHG-001',
        ];
        $netboxLookups = [
            'device_type_id' => 'Cisco ASR9000',
            'region_id' => 'Europe',
            'site_group_id' => 'DC-Frankfurt',
            'site_id' => 'FRA1-RZ1',
            'contact_id' => 'John Doe',
        ];
        $result = $this->builder->build(
            'rz_provision',
            $this->eventMeta,
            $data,
            'req-123',
            'test@example.com',
            null,
            ['netbox_lookups' => $netboxLookups]
        );

        // Verify NetBox names appear instead of IDs
        $this->assertStringContainsString('ASSET-STAMMDATEN:', $result);
        $this->assertStringContainsString('Cisco ASR9000', $result);
        $this->assertStringContainsString('STANDORT & ZUORDNUNG:', $result);
        $this->assertStringContainsString('Europe', $result);
        $this->assertStringContainsString('DC-Frankfurt', $result);
        $this->assertStringContainsString('FRA1-RZ1', $result);
        $this->assertStringContainsString('John Doe', $result);
    }

    public function testBuildRzProvisionWithEmptyFieldsShowsNichtAngegeben(): void
    {
        $data = [
            'asset_id' => 'SRV-043',
            'device_type_id' => 1,
            'region_id' => 5,
            'site_group_id' => 10,
            'site_id' => 20,
            'commission_date' => '',
            'contact_id' => 42,
            'criticality' => '',
            'change_ref' => 'CHG-002',
        ];
        $netboxLookups = [
            'device_type_id' => 'Cisco ASR9000',
            'region_id' => 'Europe',
            'site_group_id' => 'DC-Frankfurt',
            'site_id' => 'FRA1-RZ1',
            'contact_id' => 'John Doe',
        ];
        $result = $this->builder->build(
            'rz_provision',
            $this->eventMeta,
            $data,
            'req-124',
            'test@example.com',
            null,
            ['netbox_lookups' => $netboxLookups]
        );

        // Empty fields should show "(nicht angegeben)"
        $this->assertStringContainsString('(nicht angegeben)', $result);
    }

    public function testBuildRzProvisionWithoutLookupsDoesNotIncludeSummary(): void
    {
        $data = [
            'asset_id' => 'SRV-044',
            'device_type_id' => 1,
        ];
        // No context with netbox_lookups
        $result = $this->builder->build('rz_provision', $this->eventMeta, $data, 'req-125', 'test@example.com');

        // Summary should NOT be included if no lookups provided
        $this->assertStringNotContainsString('ASSET-STAMMDATEN:', $result);
        $this->assertStringNotContainsString('STANDORT & ZUORDNUNG:', $result);
    }

    public function testBuildRzRetireWithFormFieldsSummary(): void
    {
        $data = [
            'asset_id' => 'SRV-045',
            'retire_date' => '2026-03-03',
            'reason' => 'EOL',
            'contact_id' => 42,
            'followup' => 'Entsorgung',
            'tenant_id' => 1,
            'data_handling' => 'Secure Wipe',
            'data_handling_ref' => 'WIPE-123',
        ];
        $retireMeta = new EventDefinition(
            track: 'rz_assets',
            label: 'Außerbetriebnahme RZ-Asset',
            category: 'RZ',
            subjectType: 'Außerbetriebnahme',
            requiredFields: [],
        );
        $netboxLookups = [
            'contact_id' => 'Jane Smith',
            'tenant_id' => 'Company B',
        ];
        $result = $this->builder->build(
            'rz_retire',
            $retireMeta,
            $data,
            'req-126',
            'test@example.com',
            null,
            ['netbox_lookups' => $netboxLookups]
        );

        // Verify structure
        $this->assertStringContainsString('ASSET-DATEN:', $result);
        $this->assertStringContainsString('Jane Smith', $result);
        $this->assertStringContainsString('Company B', $result);
        $this->assertStringContainsString('DATA HANDLING:', $result);
        $this->assertStringContainsString('Secure Wipe', $result);
        $this->assertStringContainsString('WIPE-123', $result);
    }

    public function testBuildCheckboxFieldsDisplayJaNein(): void
    {
        $data = [
            'asset_id' => 'SRV-046',
            'device_type_id' => 1,
            'region_id' => 5,
            'site_group_id' => 10,
            'site_id' => 20,
            'commission_date' => '2026-03-03',
            'contact_id' => 42,
            'criticality' => 'hoch',
            'change_ref' => 'CHG-003',
            'monitoring_active' => true,
            'patch_process' => false,
            'access_controlled' => true,
        ];
        $netboxLookups = [
            'device_type_id' => 'Dell',
            'region_id' => 'Europe',
            'site_group_id' => 'DC',
            'site_id' => 'Site1',
            'contact_id' => 'Owner1',
        ];
        $result = $this->builder->build(
            'rz_provision',
            $this->eventMeta,
            $data,
            'req-127',
            'test@example.com',
            null,
            ['netbox_lookups' => $netboxLookups]
        );

        // Checkboxes should display Ja/Nein
        $this->assertStringContainsString('BETRIEBSBEREITSCHAFT:', $result);
        $this->assertStringContainsString('Monitoring aktiv: Ja', $result);
        $this->assertStringContainsString('Patch/Firmware-Prozess definiert: Nein', $result);
        $this->assertStringContainsString('Zugriff über berechtigte Admin-Gruppen: Ja', $result);
    }

    // ============ HTML FormFieldsSummary Tests ============

    public function testFormatFormFieldsSummaryAsHtmlReturnsValidHtml(): void
    {
        $data = [
            'asset_id' => 'SRV-050',
            'device_type_id' => 1,
            'region_id' => 5,
            'site_group_id' => 10,
            'site_id' => 20,
            'commission_date' => '2026-03-03',
            'contact_id' => 42,
            'criticality' => 'hoch',
            'change_ref' => 'CHG-005',
        ];
        $netboxLookups = [
            'device_type_id' => 'Cisco ASR9000',
            'region_id' => 'Europe',
            'site_group_id' => 'DC-Frankfurt',
            'site_id' => 'FRA1-RZ1',
            'contact_id' => 'Alice Smith',
        ];

        $result = $this->builder->formatFormFieldsSummaryAsHtml('rz_provision', $data, $netboxLookups);

        // Check basic HTML structure
        $this->assertStringContainsString('<div', $result);
        $this->assertStringContainsString('</div>', $result);
        $this->assertStringContainsString('<h4', $result);
        $this->assertStringContainsString('</h4>', $result);
        $this->assertStringContainsString('<dl', $result);
        $this->assertStringContainsString('</dl>', $result);
        $this->assertStringContainsString('<dt', $result);
        $this->assertStringContainsString('<dd', $result);
    }

    public function testFormatFormFieldsSummaryAsHtmlIncludesGroupTitles(): void
    {
        $data = [
            'asset_id' => 'SRV-051',
            'device_type_id' => 1,
            'region_id' => 5,
            'site_group_id' => 10,
            'site_id' => 20,
            'commission_date' => '2026-03-03',
            'contact_id' => 42,
            'criticality' => 'hoch',
            'change_ref' => 'CHG-006',
            'monitoring_active' => true,
            'patch_process' => true,
            'access_controlled' => false,
        ];
        $netboxLookups = [
            'device_type_id' => 'Dell',
            'region_id' => 'EU',
            'site_group_id' => 'DC',
            'site_id' => 'Site1',
            'contact_id' => 'Bob Jones',
        ];

        $result = $this->builder->formatFormFieldsSummaryAsHtml('rz_provision', $data, $netboxLookups);

        // Check for group titles
        $this->assertStringContainsString('ASSET-STAMMDATEN', $result);
        $this->assertStringContainsString('STANDORT &amp; ZUORDNUNG', $result);
        $this->assertStringContainsString('BETRIEBSBEREITSCHAFT', $result);
    }

    public function testFormatFormFieldsSummaryAsHtmlEscapesHtmlChars(): void
    {
        $data = [
            'asset_id' => 'SRV-052',
            'device_type_id' => 1,
            'region_id' => 5,
            'site_group_id' => 10,
            'site_id' => 20,
            'commission_date' => '2026-03-03',
            'contact_id' => 0,
            'criticality' => '<script>alert("xss")</script>',
            'change_ref' => 'CHG-007',
        ];
        $netboxLookups = [
            'device_type_id' => 'Dell',
            'region_id' => 'EU',
            'site_group_id' => 'DC',
            'site_id' => 'Site1',
        ];

        $result = $this->builder->formatFormFieldsSummaryAsHtml('rz_provision', $data, $netboxLookups);

        // Dangerous chars should be escaped
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testFormatFormFieldsSummaryAsHtmlRzRetire(): void
    {
        $data = [
            'asset_id' => 'SRV-053',
            'retire_date' => '2026-03-04',
            'reason' => 'EOL',
            'contact_id' => 42,
            'followup' => 'Entsorgung',
            'tenant_id' => 1,
            'data_handling' => 'Secure Wipe',
            'data_handling_ref' => 'WIPE-456',
        ];
        $netboxLookups = [
            'contact_id' => 'Carol White',
            'tenant_id' => 'Company C',
        ];

        $result = $this->builder->formatFormFieldsSummaryAsHtml('rz_retire', $data, $netboxLookups);

        // Check for rz_retire specific groups
        $this->assertStringContainsString('ASSET-DATEN', $result);
        $this->assertStringContainsString('DATA HANDLING', $result);
        $this->assertStringContainsString('Carol White', $result);
        $this->assertStringContainsString('Company C', $result);
    }

    public function testFormatFormFieldsSummaryAsHtmlDisplaysEmptyValuesAsNichtAngegeben(): void
    {
        $data = [
            'asset_id' => 'SRV-054',
            'device_type_id' => 1,
            'region_id' => 5,
            'site_group_id' => 10,
            'site_id' => 20,
            'commission_date' => '',
            'contact_id' => 0,
            'criticality' => '',
            'change_ref' => 'CHG-008',
        ];
        $netboxLookups = [
            'device_type_id' => 'Dell',
            'region_id' => 'EU',
            'site_group_id' => 'DC',
            'site_id' => 'Site1',
        ];

        $result = $this->builder->formatFormFieldsSummaryAsHtml('rz_provision', $data, $netboxLookups);

        // Empty values should display (nicht angegeben)
        $this->assertStringContainsString('(nicht angegeben)', $result);
    }
}
