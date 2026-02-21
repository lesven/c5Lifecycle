<?php
declare(strict_types=1);

namespace C5\Mail;

class MailBuilder
{
    /** Field labels for readable email body */
    private static array $labels = [
        'asset_id' => 'Asset-ID',
        'device_type' => 'Gerätetyp',
        'manufacturer' => 'Hersteller',
        'model' => 'Modell',
        'serial_number' => 'Seriennummer',
        'location' => 'Standort',
        'commission_date' => 'Datum Inbetriebnahme',
        'asset_owner' => 'Asset Owner',
        'service' => 'Service/Plattform',
        'criticality' => 'Kritikalität',
        'change_ref' => 'Change-/Ticket-Referenz',
        'monitoring_active' => 'Monitoring aktiv',
        'patch_process' => 'Patch/Firmware-Prozess definiert',
        'access_controlled' => 'Zugriff über berechtigte Admin-Gruppen',
        'retire_date' => 'Datum Stilllegung',
        'reason' => 'Grund',
        'owner_approval' => 'Freigabe durch Owner',
        'followup' => 'Folgeweg',
        'data_handling' => 'Data Handling',
        'data_handling_ref' => 'Nachweisreferenz',
        'owner' => 'Owner',
        'confirm_date' => 'Datum',
        'purpose_bound' => 'Zweckgebundener Betrieb',
        'change_process' => 'Changes nur via Change-Prozess',
        'admin_access_controlled' => 'Admin-Zugriff kontrolliert',
        'lifecycle_managed' => 'Lifecycle aktiv gemanagt',
        'admin_user' => 'Admin-User',
        'security_owner' => 'Security Owner',
        'purpose' => 'Zweck / Privileged Role',
        'disk_encryption' => 'Disk Encryption aktiv',
        'mfa_active' => 'MFA aktiv',
        'edr_active' => 'EDR/AV aktiv',
        'no_private_use' => 'Keine private Nutzung',
        'commitment_date' => 'Datum',
        'admin_tasks_only' => 'Nur Admin-Tätigkeiten',
        'no_mail_office' => 'Kein Mail/Office/Surfing',
        'no_credential_sharing' => 'Keine Weitergabe von Credentials',
        'report_loss' => 'Verlust sofort melden',
        'return_on_change' => 'Rückgabe bei Rollenwechsel/Austritt',
        'return_date' => 'Rückgabedatum',
        'return_reason' => 'Rückgabegrund',
        'condition' => 'Zustand',
        'accessories_complete' => 'Zubehör vollständig',
        'cleanup_date' => 'Datum',
        'account_removed' => 'Admin-Account entfernt/angepasst',
        'keys_revoked' => 'Keys/Zertifikate revoked',
        'device_wiped' => 'Gerät wiped oder reprovisioniert',
        'ticket_ref' => 'Ticket-Referenz',
        'tenant_name' => 'Mandant',
    ];

    /** Fields to skip in the email body (raw IDs, not human-readable) */
    private static array $skipFields = ['tenant_id'];

    public static function build(array $event, array $data, string $requestId): string
    {
        $lines = [];
        $lines[] = '═══════════════════════════════════════════';
        $lines[] = '  C5 EVIDENCE – ' . strtoupper($event['label']);
        $lines[] = '═══════════════════════════════════════════';
        $lines[] = '';
        $lines[] = 'Event:       ' . $event['label'];
        $lines[] = 'Kategorie:   ' . $event['category'];
        $lines[] = 'Request-ID:  ' . $requestId;
        $lines[] = 'Zeitstempel: ' . date('Y-m-d H:i:s T');
        $lines[] = '';
        $lines[] = '───────────────────────────────────────────';
        $lines[] = '  ERFASSTE DATEN';
        $lines[] = '───────────────────────────────────────────';
        $lines[] = '';

        foreach ($data as $key => $value) {
            if (in_array($key, self::$skipFields, true)) {
                continue;
            }
            $label = self::$labels[$key] ?? $key;
            if (is_bool($value)) {
                $display = $value ? 'Ja' : 'Nein';
            } else {
                $display = (string) $value;
            }
            $lines[] = str_pad($label . ':', 38) . $display;
        }

        $lines[] = '';
        $lines[] = '───────────────────────────────────────────';
        $lines[] = 'Diese E-Mail wurde automatisch vom C5 Evidence Tool erstellt.';
        $lines[] = 'Request-ID: ' . $requestId;

        return implode("\r\n", $lines);
    }
}
