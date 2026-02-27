<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\ValueObject\EventDefinition;

final class EvidenceMailBuilder
{
    private const SKIP_FIELDS = ['tenant_id', 'region_id', 'site_group_id', 'site_id'];

    public function __construct(
        private readonly FieldLabelRegistry $labelRegistry = new FieldLabelRegistry(),
    ) {
    }

    public function build(EventDefinition $event, array $data, string $requestId, ?string $submittedBy = null): string
    {
        $lines = [];
        $lines[] = '═══════════════════════════════════════════';
        $lines[] = '  C5 EVIDENCE – ' . strtoupper($event->label);
        $lines[] = '═══════════════════════════════════════════';
        $lines[] = '';
        $lines[] = 'Event:       ' . $event->label;
        $lines[] = 'Kategorie:   ' . $event->category;
        $lines[] = 'Request-ID:  ' . $requestId;
        $lines[] = 'Zeitstempel: ' . date('Y-m-d H:i:s T');
        $lines[] = '';
        $lines[] = '───────────────────────────────────────────';
        $lines[] = '  ERFASSTE DATEN';
        $lines[] = '───────────────────────────────────────────';
        $lines[] = '';

        foreach ($data as $key => $value) {
            if (in_array($key, self::SKIP_FIELDS, true)) {
                continue;
            }
            $label = $this->labelRegistry->get($key);
            if (is_bool($value)) {
                $display = $value ? 'Ja' : 'Nein';
            } else {
                $display = (string) $value;
            }
            $lines[] = str_pad($label . ':', 38) . $display;
        }

        $lines[] = '';

        if ($submittedBy !== null) {
            $lines[] = '───────────────────────────────────────────';
            $lines[] = str_pad('Eingetragen von (System):', 38) . $submittedBy;
            $lines[] = '';
        }

        $lines[] = '───────────────────────────────────────────';
        $lines[] = 'Diese E-Mail wurde automatisch vom C5 Evidence Tool erstellt.';
        $lines[] = 'Request-ID: ' . $requestId;

        return implode("\r\n", $lines);
    }
}
