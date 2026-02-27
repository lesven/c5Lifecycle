<?php

declare(strict_types=1);

namespace App\Domain\Service;

/**
 * Single source of truth for human-readable form field labels (German).
 *
 * Used by EvidenceMailBuilder for email formatting and exposed via
 * API endpoint for frontend consumption.
 */
final class FieldLabelRegistry
{
    /** @var array<string, string> */
    private const LABELS = [
        'asset_id' => 'Asset-ID',
        'device_type' => 'Gerätetyp',
        'manufacturer' => 'Hersteller',
        'model' => 'Modell',
        'serial_number' => 'Seriennummer',
        'region_id' => 'Region',
        'region_name' => 'Region (Name)',
        'site_group_id' => 'Standortgruppe',
        'site_group_name' => 'Standortgruppe (Name)',
        'site_id' => 'Standort',
        'site_name' => 'Standort (Name)',
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

    /** @return array<string, string> All field labels */
    public function all(): array
    {
        return self::LABELS;
    }

    /** Get label for a field key, returns the key itself as fallback */
    public function get(string $fieldKey): string
    {
        return self::LABELS[$fieldKey] ?? $fieldKey;
    }
}
