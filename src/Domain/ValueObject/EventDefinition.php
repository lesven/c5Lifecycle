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
     * @param string   $track          Track identifier (e.g. 'rz_assets', 'admin_devices')
     * @param string   $label          Human readable label (German)
     * @param string   $category       Short category code ('RZ' or 'ADM')
     * @param string   $subjectType    Subject type for mail subject (e.g. 'Inbetriebnahme')
     * @param string[] $requiredFields List of required form field names
     */
    public function __construct(
        public string $track,
        public string $label,
        public string $category,
        public string $subjectType,
        public array $requiredFields,
    ) {
    }

    /**
     * @deprecated Bridge method for legacy code that still expects arrays.
     *             Will be removed once all callers are migrated.
     * @return array{track: string, label: string, category: string, subject_type: string, required_fields: string[]}
     */
    public function toArray(): array
    {
        return [
            'track' => $this->track,
            'label' => $this->label,
            'category' => $this->category,
            'subject_type' => $this->subjectType,
            'required_fields' => $this->requiredFields,
        ];
    }
}
