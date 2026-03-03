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
}
