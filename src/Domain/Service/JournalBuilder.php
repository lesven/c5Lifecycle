<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\ValueObject\EventDefinition;

final class JournalBuilder
{
    public function build(string $eventType, EventDefinition $eventMeta, array $data, string $requestId, string $evidenceTo, ?string $submittedBy = null): string
    {
        $label = $eventMeta->label;
        $assetId = $data['asset_id'] ?? 'UNKNOWN';
        $date = date('Y-m-d');

        $lines = [
            "C5 Evidence: {$label}",
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

        $lines[] = "Evidence-Mail versendet an: {$evidenceTo}";

        // Additional info for retirement events
        if ($eventType === 'rz_retire') {
            if (!empty($data['data_handling'])) {
                $lines[] = "Data-Handling-Methode: {$data['data_handling']}";
            }
            if (!empty($data['data_handling_ref'])) {
                $lines[] = "Nachweisreferenz: {$data['data_handling_ref']}";
            }
        }

        return implode("\n", $lines);
    }
}
