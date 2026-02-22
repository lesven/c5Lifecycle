<?php

declare(strict_types=1);

namespace App\Application\Validator;

final class EventDataValidator
{
    /**
     * Validate form data against event definition.
     *
     * @return array<string, string> field => error message
     */
    public function validate(string $eventType, array $event, array $data): array
    {
        $errors = [];

        foreach ($event['required_fields'] as $field) {
            $val = $data[$field] ?? null;
            if ($val === false || $val === null || $val === '') {
                $errors[$field] = 'Pflichtfeld';
            }
        }

        // Conditional: rz_retire — data_handling_ref required if data_handling != "Nicht relevant"
        if ($eventType === 'rz_retire') {
            $dh = $data['data_handling'] ?? '';
            if ($dh !== '' && $dh !== 'Nicht relevant') {
                if (empty($data['data_handling_ref'])) {
                    $errors['data_handling_ref'] = 'Pflichtfeld (Data Handling ≠ Nicht relevant)';
                }
            }
        }

        // Conditional: admin_access_cleanup — ticket_ref required if device_wiped is not checked
        if ($eventType === 'admin_access_cleanup') {
            if (empty($data['device_wiped'])) {
                if (empty($data['ticket_ref'])) {
                    $errors['ticket_ref'] = 'Pflichtfeld (Wipe nicht abgeschlossen)';
                }
            }
        }

        return $errors;
    }
}
