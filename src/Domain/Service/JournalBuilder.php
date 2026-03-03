<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\ValueObject\EventDefinition;

final class JournalBuilder
{
    public function __construct(
        private readonly FieldLabelRegistry $fieldLabelRegistry,
    ) {
    }

    public function build(string $eventType, EventDefinition $eventMeta, array $data, string $requestId, string $evidenceTo, ?string $submittedBy = null, array $context = []): string
    {
        $label = $eventMeta->label;
        $assetId = $data['asset_id'] ?? 'UNKNOWN';
        $date = date('Y-m-d');
        $isReProvision = !empty($context['is_re_provision']);

        $header = $isReProvision ? "C5 Evidence: {$label} (Re-Provision)" : "C5 Evidence: {$label}";

        $lines = [
            $header,
            "Request-ID: {$requestId}",
            "Asset-ID: {$assetId}",
            "Datum: {$date}",
        ];

        // Add submitter info if available
        if (!empty($data['asset_owner'])) {
            $lines[] = "Erfasst von: {$data['asset_owner']}";
        } elseif (!empty($data['admin_user'])) {
            $lines[] = "Erfasst von: {$data['admin_user']}";
        } elseif (!empty($data['owner'])) {
            $lines[] = "Erfasst von: {$data['owner']}";
        }

        if ($submittedBy !== null) {
            $lines[] = "System-User: {$submittedBy}";
        }

        // Add form fields summary for rz_provision and rz_retire (if NetBox lookups available)
        if (in_array($eventType, ['rz_provision', 'rz_retire']) && !empty($context['netbox_lookups'])) {
            $fieldsSummary = $this->formatFormFieldsSummary($eventType, $data, $context['netbox_lookups']);
            if ($fieldsSummary !== '') {
                $lines[] = '';
                $lines[] = $fieldsSummary;
            }
        }

        $lines[] = '';
        $lines[] = "Evidence-Mail versendet an: {$evidenceTo}";

        if (!empty($data['change_ref'])) {
            $lines[] = "Change-Ref: {$data['change_ref']}";
        }

        // Additional info for retirement events
        if ($eventType === 'rz_retire') {
            if (!empty($data['data_handling'])) {
                $lines[] = "Data-Handling-Methode: {$data['data_handling']}";
            }
            if (!empty($data['data_handling_ref'])) {
                $lines[] = "Nachweisreferenz: {$data['data_handling_ref']}";
            }
        }

        // Confirmation checkboxes for owner confirmation events
        if ($eventType === 'rz_owner_confirm') {
            $checkboxFields = ['purpose_bound', 'admin_access_controlled', 'maintenance_window_ok'];
            $lines[] = '';
            $lines[] = 'Bestätigungen:';
            foreach ($checkboxFields as $field) {
                $label = $this->fieldLabelRegistry->get($field);
                $value = isset($data[$field]) && $data[$field] ? 'Ja' : 'Nein';
                $lines[] = "  - {$label}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format form fields summary for rz_provision and rz_retire events.
     * Groups fields and replaces IDs with human-readable names from NetBox.
     *
     * Public method for use in Custom Fields and Journal comments.
     *
     * @param string $eventType Event type (rz_provision or rz_retire)
     * @param array $data Form submission data
     * @param array $netboxLookups Associative array of ID->Name lookups from NetBox
     * @return string Formatted summary text
     */
    public function formatFormFieldsSummary(string $eventType, array $data, array $netboxLookups): string
    {
        $lines = [];
        $lines[] = '─────────────────────────────────────────────────────';

        if ($eventType === 'rz_provision') {
            // Asset-Stammdaten
            $lines[] = 'ASSET-STAMMDATEN:';
            $assetStandardFields = [
                'asset_id' => ['value' => 'asset_id'],
                'device_type' => ['value' => 'device_type_id', 'lookup' => true],
                'manufacturer' => ['value' => 'manufacturer'],
                'model' => ['value' => 'model'],
                'serial_number' => ['value' => 'serial_number'],
            ];
            $lines = array_merge($lines, $this->renderFieldGroups($assetStandardFields, $data, $netboxLookups));

            // Standort & Zuordnung
            $lines[] = '';
            $lines[] = 'STANDORT & ZUORDNUNG:';
            $locationFields = [
                'region_id' => ['value' => 'region_id', 'lookup' => true],
                'site_group_id' => ['value' => 'site_group_id', 'lookup' => true],
                'site_id' => ['value' => 'site_id', 'lookup' => true],
                'commission_date' => ['value' => 'commission_date'],
                'asset_owner' => ['value' => 'contact_id', 'lookup' => true],
                'service' => ['value' => 'service'],
                'criticality' => ['value' => 'criticality'],
                'change_ref' => ['value' => 'change_ref'],
                'tenant_id' => ['value' => 'tenant_id', 'lookup' => true],
            ];
            $lines = array_merge($lines, $this->renderFieldGroups($locationFields, $data, $netboxLookups));

            // Betriebsbereitschaft
            $lines[] = '';
            $lines[] = 'BETRIEBSBEREITSCHAFT:';
            $operationalFields = [
                'monitoring_active' => ['value' => 'monitoring_active', 'type' => 'checkbox'],
                'patch_process' => ['value' => 'patch_process', 'type' => 'checkbox'],
                'access_controlled' => ['value' => 'access_controlled', 'type' => 'checkbox'],
            ];
            $lines = array_merge($lines, $this->renderFieldGroups($operationalFields, $data, $netboxLookups));
        } elseif ($eventType === 'rz_retire') {
            // Asset-Daten
            $lines[] = 'ASSET-DATEN:';
            $assetRetireFields = [
                'asset_id' => ['value' => 'asset_id'],
                'retire_date' => ['value' => 'retire_date'],
                'reason' => ['value' => 'reason'],
                'owner_approval' => ['value' => 'contact_id', 'lookup' => true],
                'followup' => ['value' => 'followup'],
                'tenant_id' => ['value' => 'tenant_id', 'lookup' => true],
            ];
            $lines = array_merge($lines, $this->renderFieldGroups($assetRetireFields, $data, $netboxLookups));

            // Data Handling
            $lines[] = '';
            $lines[] = 'DATA HANDLING:';
            $dataHandlingFields = [
                'data_handling' => ['value' => 'data_handling'],
                'data_handling_ref' => ['value' => 'data_handling_ref'],
            ];
            $lines = array_merge($lines, $this->renderFieldGroups($dataHandlingFields, $data, $netboxLookups));
        }

        $lines[] = '─────────────────────────────────────────────────────';

        return implode("\n", $lines);
    }

    /**
     * Render field groups with labels and values.
     *
     * @param array $fieldDefinitions Field definitions with structure [label => ['value' => key, 'lookup' => bool, 'type' => string]]
     * @param array $data Form submission data
     * @param array $netboxLookups Lookup table (id => name)
     * @return array Array of formatted lines
     */
    private function renderFieldGroups(array $fieldDefinitions, array $data, array $netboxLookups): array
    {
        $lines = [];

        foreach ($fieldDefinitions as $fieldKey => $definition) {
            $fieldLabel = $this->fieldLabelRegistry->get($fieldKey);
            $dataKey = $definition['value'];
            $isLookup = $definition['lookup'] ?? false;
            $fieldType = $definition['type'] ?? 'text';

            $value = $data[$dataKey] ?? null;

            // Format the value
            if ($fieldType === 'checkbox') {
                $displayValue = (isset($data[$dataKey]) && $data[$dataKey]) ? 'Ja' : 'Nein';
            } elseif ($isLookup && $value !== null && $value !== '') {
                $lookupKey = $dataKey;
                $displayValue = $netboxLookups[$lookupKey] ?? (string) $value;
            } elseif ($value === null || $value === '') {
                $displayValue = '(nicht angegeben)';
            } else {
                $displayValue = (string) $value;
            }

            // Sanitize output for HTML-safe display in text/plain context
            $displayValue = htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8');
            $lines[] = "  • {$fieldLabel}: {$displayValue}";
        }

        return $lines;
    }
}
