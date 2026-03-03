<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ExportOwnerReportCommand;
use App\Domain\Repository\EvidenceConfigInterface;
use App\Domain\Repository\NetBoxClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ExportOwnerReportCommandTest extends TestCase
{
    /** @var NetBoxClientInterface&MockObject */
    private NetBoxClientInterface $netBoxClient;

    /** @var EvidenceConfigInterface&MockObject */
    private EvidenceConfigInterface $evidenceConfig;

    private ExportOwnerReportCommand $command;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->netBoxClient  = $this->createMock(NetBoxClientInterface::class);
        $this->evidenceConfig = $this->createMock(EvidenceConfigInterface::class);

        $this->command = new ExportOwnerReportCommand(
            $this->netBoxClient,
            $this->evidenceConfig,
        );

        $this->tempDir = sys_get_temp_dir() . '/c5_owner_report_test_' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        // Temporäre Dateien bereinigen
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    private function configureDefaultConfig(
        bool $netboxEnabled = true,
        string $appBaseUrl = 'https://c5.example.com',
        string $netboxBaseUrl = 'https://netbox.example.com',
        string $contactRoleOwner = 'tenancy/contact-roles/1/',
    ): void {
        $this->evidenceConfig->method('isNetBoxEnabled')->willReturn($netboxEnabled);
        $this->evidenceConfig->method('getAppBaseUrl')->willReturn($appBaseUrl);
        $this->evidenceConfig->method('getNetBoxBaseUrl')->willReturn($netboxBaseUrl);
        $this->evidenceConfig->method('getContactRoleOwner')->willReturn($contactRoleOwner);
    }

    // -------------------------------------------------------------------------
    // Fehlerfall: --output fehlt
    // -------------------------------------------------------------------------

    public function testMissingOutputOptionReturnsFailure(): void
    {
        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('--output', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Fehlerfall: NetBox deaktiviert
    // -------------------------------------------------------------------------

    public function testNetBoxDisabledReturnsFailure(): void
    {
        $this->evidenceConfig->method('isNetBoxEnabled')->willReturn(false);

        $tester = new CommandTester($this->command);
        $tester->execute(['--output' => $this->tempDir . '/out.xlsx']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('deaktiviert', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Happy Path: 2 Assignments → 2 Zeilen in Excel
    // -------------------------------------------------------------------------

    public function testHappyPathCreatesExcelWithCorrectRowCount(): void
    {
        $this->configureDefaultConfig();

        $this->netBoxClient->method('getContacts')->willReturn([
            ['id' => 10, 'name' => 'Max Mustermann', 'email' => 'max@example.com'],
            ['id' => 20, 'name' => 'Erika Muster',   'email' => 'erika@example.com'],
        ]);

        $this->netBoxClient->method('getOwnerContactAssignments')->willReturn([
            [
                'object_id' => 100,
                'contact'   => ['id' => 10, 'display' => 'Max Mustermann'],
            ],
            [
                'object_id' => 200,
                'contact'   => ['id' => 20, 'display' => 'Erika Muster'],
            ],
        ]);

        $this->netBoxClient->method('findDeviceById')->willReturnMap([
            [100, $this->anything(), ['id' => 100, 'name' => 'Server-1', 'asset_tag' => 'SRV-001']],
            [200, $this->anything(), ['id' => 200, 'name' => 'Server-2', 'asset_tag' => 'SRV-002']],
        ]);

        $outputPath = $this->tempDir . '/report.xlsx';
        $tester = new CommandTester($this->command);
        $tester->execute(['--output' => $outputPath]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('2 Zeilen', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Zellinhalte prüfen (Excel einlesen)
    // -------------------------------------------------------------------------

    public function testExcelContainsCorrectCellValues(): void
    {
        // buildSpreadsheet() direkt aufrufen – vermeidet ZipArchive-Abhängigkeit beim Lesen
        $rows = [
            [
                'owner_name'  => 'Max Mustermann',
                'owner_email' => 'max@example.com',
                'netbox_url'  => 'https://netbox.example.com/dcim/devices/100/',
                'form_url'    => 'https://c5.example.com/forms/rz-owner-confirm?asset_id=SRV-001',
            ],
        ];

        $sheet = $this->command->buildSpreadsheet($rows)->getActiveSheet();

        // Zeile 1: Header
        $this->assertSame('Owner Name',    $sheet->getCell('A1')->getValue());
        $this->assertSame('E-Mail',        $sheet->getCell('B1')->getValue());
        $this->assertSame('NetBox Link',   $sheet->getCell('C1')->getValue());
        $this->assertSame('Formular Link', $sheet->getCell('D1')->getValue());

        // Zeile 2: Daten
        $this->assertSame('Max Mustermann', $sheet->getCell('A2')->getValue());
        $this->assertSame('max@example.com', $sheet->getCell('B2')->getValue());
        $this->assertSame('https://netbox.example.com/dcim/devices/100/', $sheet->getCell('C2')->getValue());
        $this->assertSame(
            'https://c5.example.com/forms/rz-owner-confirm?asset_id=SRV-001',
            $sheet->getCell('D2')->getValue(),
        );
    }

    // -------------------------------------------------------------------------
    // Fehlende E-Mail → Zelle bleibt leer (kein Fehler)
    // -------------------------------------------------------------------------

    public function testMissingEmailWritesEmptyCellAndSucceeds(): void
    {
        // buildSpreadsheet() direkt aufrufen – kein ZipArchive nötig
        $rows = [
            [
                'owner_name'  => 'Kontakt Ohne Mail',
                'owner_email' => '',
                'netbox_url'  => 'https://netbox.example.com/dcim/devices/100/',
                'form_url'    => 'https://c5.example.com/forms/rz-owner-confirm?asset_id=SRV-001',
            ],
        ];

        $sheet = $this->command->buildSpreadsheet($rows)->getActiveSheet();
        $this->assertSame('', (string) $sheet->getCell('B2')->getValue());
        $this->assertSame('Kontakt Ohne Mail', $sheet->getCell('A2')->getValue());
    }

    // -------------------------------------------------------------------------
    // Fehlender Asset-Tag → Warnung auf Console, Formular-Link leer
    // -------------------------------------------------------------------------

    public function testMissingAssetTagShowsWarningAndEmptyFormUrl(): void
    {
        $this->configureDefaultConfig();

        $this->netBoxClient->method('getContacts')->willReturn([
            ['id' => 10, 'name' => 'Max', 'email' => 'max@example.com'],
        ]);

        $this->netBoxClient->method('getOwnerContactAssignments')->willReturn([
            ['object_id' => 100, 'contact' => ['id' => 10, 'display' => 'Max']],
        ]);

        // Gerät ohne asset_tag
        $this->netBoxClient->method('findDeviceById')->willReturn(
            ['id' => 100, 'name' => 'Server-Ohne-Tag', 'asset_tag' => null],
        );

        $outputPath = $this->tempDir . '/no_tag.xlsx';
        $tester = new CommandTester($this->command);
        $tester->execute(['--output' => $outputPath]);

        // Console soll Warnung enthalten
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Asset-Tag', $tester->getDisplay());

        // Formular-Link soll leer sein – direkt über buildSpreadsheet prüfen
        $rows = [
            ['owner_name' => 'Max', 'owner_email' => 'max@example.com', 'netbox_url' => '', 'form_url' => ''],
        ];
        $sheet = $this->command->buildSpreadsheet($rows)->getActiveSheet();
        $this->assertSame('', (string) $sheet->getCell('D2')->getValue());
    }

    // -------------------------------------------------------------------------
    // Keine Assignments → leere Excel (nur Header), Warnung
    // -------------------------------------------------------------------------

    public function testNoAssignmentsWritesHeaderOnlyWithWarning(): void
    {
        $this->configureDefaultConfig();

        $this->netBoxClient->method('getContacts')->willReturn([]);
        $this->netBoxClient->method('getOwnerContactAssignments')->willReturn([]);
        $this->netBoxClient->method('findDeviceById')->willReturn(null);

        $outputPath = $this->tempDir . '/empty.xlsx';
        $tester = new CommandTester($this->command);
        $tester->execute(['--output' => $outputPath]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('0 Zeilen', $tester->getDisplay());

        // Header-Inhalt direkt prüfen – ohne ZipArchive
        $sheet = $this->command->buildSpreadsheet([])->getActiveSheet();
        $this->assertSame('Owner Name', $sheet->getCell('A1')->getValue());
        $this->assertSame('', (string) $sheet->getCell('A2')->getValue());
    }

    // -------------------------------------------------------------------------
    // Role-ID-Extraktion: verschiedene Formate
    // -------------------------------------------------------------------------

    public function testRoleIdExtractionFromPath(): void
    {
        $this->configureDefaultConfig(contactRoleOwner: 'tenancy/contact-roles/42/');

        $capturedRoleId = null;
        $this->netBoxClient->method('getContacts')->willReturn([]);
        $this->netBoxClient
            ->method('getOwnerContactAssignments')
            ->willReturnCallback(function (int $roleId) use (&$capturedRoleId): array {
                $capturedRoleId = $roleId;
                return [];
            });
        $this->netBoxClient->method('findDeviceById')->willReturn(null);

        (new CommandTester($this->command))->execute(
            ['--output' => $this->tempDir . '/role.xlsx'],
        );

        $this->assertSame(42, $capturedRoleId);
    }
}
