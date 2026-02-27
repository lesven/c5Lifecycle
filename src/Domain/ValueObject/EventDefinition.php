<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Immutable definition of a lifecycle event type.
 *
 * Replaces the previous array{track, label, category, subject_type, required_fields}
 * structure with a strongly-typed value object.
 */
final readonly class EventDefinition
{
    /**
     * @param string   $track            Track identifier (e.g. 'rz_assets', 'admin_devices')
     * @param string   $label            Human readable label (German)
     * @param string   $category         Short category code ('RZ' or 'ADM')
     * @param string   $subjectType      Subject type for mail subject (e.g. 'Inbetriebnahme')
     * @param string[] $requiredFields   List of required form field names
     * @param array<string, array{when: array{field: string, operator: string, value: mixed}, then: string}> $conditionalRules Declarative conditional-required rules
     */
    public function __construct(
        public string $track,
        public string $label,
        public string $category,
        public string $subjectType,
        public array $requiredFields,
        public array $conditionalRules = [],
    ) {
    }
}
