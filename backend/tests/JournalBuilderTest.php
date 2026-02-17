<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\NetBox\JournalBuilder;
use PHPUnit\Framework\TestCase;

class JournalBuilderTest extends TestCase
{
    private array $eventMeta;

    protected function setUp(): void
    {
        $this->eventMeta = [
            'label' => 'Inbetriebnahme RZ-Asset',
            'category' => 'RZ',
        ];
    }

    public function testBuildContainsEventLabel(): void
    {
        $result = JournalBuilder::build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'test@example.com');
        $this->assertStringContainsString('C5 Evidence: Inbetriebnahme RZ-Asset', $result);
    }

    public function testBuildContainsRequestId(): void
    {
        $result = JournalBuilder::build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'test@example.com');
        $this->assertStringContainsString('Request-ID: req-123', $result);
    }

    public function testBuildContainsAssetId(): void
    {
        $result = JournalBuilder::build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'test@example.com');
        $this->assertStringContainsString('Asset-ID: SRV-001', $result);
    }

    public function testBuildContainsDate(): void
    {
        $result = JournalBuilder::build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'test@example.com');
        $this->assertStringContainsString('Datum: ' . date('Y-m-d'), $result);
    }

    public function testBuildContainsEvidenceRecipient(): void
    {
        $result = JournalBuilder::build('rz_provision', $this->eventMeta, ['asset_id' => 'SRV-001'], 'req-123', 'rz-evidence@company.de');
        $this->assertStringContainsString('Evidence-Mail versendet an: rz-evidence@company.de', $result);
    }

    public function testBuildContainsAssetOwnerAsSubmitter(): void
    {
        $data = ['asset_id' => 'SRV-001', 'asset_owner' => 'Team Platform'];
        $result = JournalBuilder::build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com');
        $this->assertStringContainsString('Erfasst von: Team Platform', $result);
    }

    public function testBuildContainsAdminUserAsSubmitter(): void
    {
        $data = ['asset_id' => 'WS-001', 'admin_user' => 'admin1'];
        $result = JournalBuilder::build('admin_provision', ['label' => 'Inbetriebnahme Admin-Endgerät', 'category' => 'ADM'], $data, 'req-456', 'test@example.com');
        $this->assertStringContainsString('Erfasst von: admin1', $result);
    }

    public function testBuildRzRetireIncludesDataHandling(): void
    {
        $data = [
            'asset_id' => 'SRV-001',
            'data_handling' => 'Secure Wipe',
            'data_handling_ref' => 'WIPE-2024-001',
        ];
        $retireMeta = ['label' => 'Außerbetriebnahme RZ-Asset', 'category' => 'RZ'];
        $result = JournalBuilder::build('rz_retire', $retireMeta, $data, 'req-789', 'test@example.com');
        $this->assertStringContainsString('Data-Handling-Methode: Secure Wipe', $result);
        $this->assertStringContainsString('Nachweisreferenz: WIPE-2024-001', $result);
    }

    public function testBuildNonRetireEventDoesNotIncludeDataHandling(): void
    {
        $data = ['asset_id' => 'SRV-001', 'data_handling' => 'Secure Wipe'];
        $result = JournalBuilder::build('rz_provision', $this->eventMeta, $data, 'req-123', 'test@example.com');
        $this->assertStringNotContainsString('Data-Handling-Methode', $result);
    }

    public function testBuildHandlesMissingAssetId(): void
    {
        $result = JournalBuilder::build('rz_provision', $this->eventMeta, [], 'req-123', 'test@example.com');
        $this->assertStringContainsString('Asset-ID: UNKNOWN', $result);
    }
}
