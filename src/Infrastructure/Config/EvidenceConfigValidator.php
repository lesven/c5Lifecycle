<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use RuntimeException;

/**
 * Validates that all required keys are present in C5 evidence configuration.
 *
 * Extracted from EvidenceConfig to keep the config class a pure readonly DTO.
 */
final class EvidenceConfigValidator
{
    private const REQUIRED_KEYS = [
        'smtp.from_address',
        'smtp.from_name',
        'evidence.rz_assets.to',
        'evidence.admin_devices.to',
    ];

    /**
     * Assert that all required config keys have non-empty values.
     *
     * @param callable(string): mixed $getter Dot-notation value lookup, e.g. $config->get(...)
     * @throws RuntimeException if any required key is missing
     */
    public static function assertValid(callable $getter): void
    {
        $missing = [];
        foreach (self::REQUIRED_KEYS as $key) {
            $value = $getter($key);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Fehlende Pflicht-Konfiguration in c5_evidence.yaml: ' . implode(', ', $missing)
            );
        }
    }
}
