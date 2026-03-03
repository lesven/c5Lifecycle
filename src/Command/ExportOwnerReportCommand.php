<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Repository\EvidenceConfigInterface;
use App\Domain\Repository\NetBoxClientInterface;
use App\Domain\ValueObject\RequestId;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export-owner-report',
    description: 'Exportiert eine Excel-Tabelle aller NetBox-Asset-Owner mit Links zum Owner-Confirm-Formular.',
)]
final class ExportOwnerReportCommand extends Command
{
    public function __construct(
        private readonly NetBoxClientInterface $netBoxClient,
        private readonly EvidenceConfigInterface $evidenceConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Pfad zur Ausgabe-Excel-Datei (z.B. /tmp/owner-report.xlsx)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $requestId = RequestId::generate()->toString();

        $outputPath = $input->getOption('output');
        if (!is_string($outputPath) || $outputPath === '') {
            $io->error('Option --output ist erforderlich. Beispiel: --output=/tmp/owner-report.xlsx');
            return Command::FAILURE;
        }

        if (!$this->evidenceConfig->isNetBoxEnabled()) {
            $io->error('NetBox ist in c5_evidence.yaml deaktiviert (netbox.enabled: false).');
            return Command::FAILURE;
        }

        $io->title('C5 Owner Report Export');
        $io->text("Request-ID: {$requestId}");

        // --- 1. Contacts laden ---
        $io->section('Lade Contacts aus NetBox ...');
        $contacts = $this->netBoxClient->getContacts($requestId);
        $contactMap = [];
        foreach ($contacts as $contact) {
            $id = (int) ($contact['id'] ?? 0);
            if ($id > 0) {
                $contactMap[$id] = $contact;
            }
        }
        $io->text(sprintf('%d Contacts geladen.', count($contactMap)));

        // --- 2. Owner-Role-ID aus Config extrahieren ---
        $roleId = $this->extractRoleId($this->evidenceConfig->getContactRoleOwner());
        $io->text(sprintf('Owner-Role-ID: %d', $roleId));

        // --- 3. Contact-Assignments laden ---
        $io->section('Lade Owner-Contact-Assignments aus NetBox ...');
        $assignments = $this->netBoxClient->getOwnerContactAssignments($roleId, $requestId);
        $io->text(sprintf('%d Assignments geladen.', count($assignments)));

        if ($assignments === []) {
            $io->warning('Keine Assignments gefunden. Die Excel-Datei wird nur einen Header enthalten.');
        }

        // --- 4. Zeilen aufbauen ---
        $netboxBaseUrl = $this->evidenceConfig->getNetBoxBaseUrl();
        $appBaseUrl    = $this->evidenceConfig->getAppBaseUrl();
        $deviceCache   = [];
        $rows          = [];

        foreach ($assignments as $assignment) {
            $deviceId  = (int) ($assignment['object_id'] ?? 0);
            $contactId = (int) ($assignment['contact']['id'] ?? 0);

            // Owner-Name und -E-Mail aus Contact-Map auflösen
            $contact    = $contactMap[$contactId] ?? null;
            $ownerName  = $contact['name'] ?? (string) ($assignment['contact']['display'] ?? '');
            $ownerEmail = (string) ($contact['email'] ?? '');

            // Device-Daten (gecacht, um N+1-Lookups zu minimieren)
            if ($deviceId > 0 && !array_key_exists($deviceId, $deviceCache)) {
                $deviceCache[$deviceId] = $this->netBoxClient->findDeviceById($deviceId, $requestId);
            }
            $device   = $deviceId > 0 ? ($deviceCache[$deviceId] ?? null) : null;
            $assetTag = (string) ($device['asset_tag'] ?? '');

            if ($assetTag === '') {
                $io->warning(sprintf(
                    'Gerät #%d hat keinen Asset-Tag – Formular-Link wird leer gelassen.',
                    $deviceId,
                ));
            }

            $netboxDeviceUrl = $netboxBaseUrl !== ''
                ? "{$netboxBaseUrl}/dcim/devices/{$deviceId}/"
                : '';

            $formUrl = ($appBaseUrl !== '' && $assetTag !== '')
                ? "{$appBaseUrl}/forms/rz-owner-confirm?asset_id=" . rawurlencode($assetTag)
                : '';

            $rows[] = [
                'owner_name'  => $ownerName,
                'owner_email' => $ownerEmail,
                'netbox_url'  => $netboxDeviceUrl,
                'form_url'    => $formUrl,
            ];
        }

        // --- 5. Excel aufbauen ---
        $io->section('Erstelle Excel-Datei ...');
        $spreadsheet = $this->buildSpreadsheet($rows);

        // --- 6. Speichern ---
        $outputDir = dirname($outputPath);
        if ($outputDir !== '' && !is_dir($outputDir)) {
            if (!mkdir($outputDir, 0o755, recursive: true) && !is_dir($outputDir)) {
                $io->error("Ausgabe-Verzeichnis konnte nicht angelegt werden: {$outputDir}");
                return Command::FAILURE;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        $io->success(sprintf(
            'Owner-Report mit %d Zeilen gespeichert: %s',
            count($rows),
            $outputPath,
        ));

        return Command::SUCCESS;
    }

    /**
     * Extrahiert die numerische Role-ID aus einem NetBox-Pfad oder einer reinen Zahl.
     *
     * Beispiele:
     *   "tenancy/contact-roles/1/" → 1
     *   "1"                        → 1
     *   "owner"                    → 0 (kein Filter)
     */
    private function extractRoleId(string $contactRoleOwner): int
    {
        // Zahl am Ende eines Pfades: ".../123/" oder ".../123"
        if (preg_match('/\/(\d+)\/?$/', $contactRoleOwner, $matches)) {
            return (int) $matches[1];
        }
        // Reine Zahl
        if (preg_match('/^\d+$/', $contactRoleOwner)) {
            return (int) $contactRoleOwner;
        }
        return 0;
    }

    /**
     * Builds a Spreadsheet object from the given rows.
     *
     * Exposed as public to allow direct inspection in unit tests without
     * requiring disk I/O or the ZipArchive extension.
     *
     * @param array<int, array{owner_name: string, owner_email: string, netbox_url: string, form_url: string}> $rows
     */
    public function buildSpreadsheet(array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Owner Report');

        // Header
        $headers = ['Owner Name', 'E-Mail', 'NetBox Link', 'Formular Link'];
        foreach ($headers as $col => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($col + 1);
            $cell = $sheet->getCell("{$colLetter}1");
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
        }

        // Datenzeilen
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;

            $sheet->getCell("A{$excelRow}")->setValue($row['owner_name']);
            $sheet->getCell("B{$excelRow}")->setValue($row['owner_email']);

            // Spalte 3: NetBox-Link
            if ($row['netbox_url'] !== '') {
                $sheet->getCell("C{$excelRow}")
                    ->setValue($row['netbox_url']);
                $sheet->getCell("C{$excelRow}")
                    ->getStyle()->getFont()
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0563C1'))
                    ->setUnderline(Font::UNDERLINE_SINGLE);
            }

            // Spalte 4: Formular-Link
            if ($row['form_url'] !== '') {
                $sheet->getCell("D{$excelRow}")
                    ->setValue($row['form_url']);
                $sheet->getCell("D{$excelRow}")
                    ->getStyle()->getFont()
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0563C1'))
                    ->setUnderline(Font::UNDERLINE_SINGLE);
            }
        }

        // Spaltenbreiten automatisch anpassen
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
