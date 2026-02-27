<?php

declare(strict_types=1);

namespace App\Application\Validator;

use App\Domain\ValueObject\EventDefinition;

final class EventDataValidator
{
    /**
     * Validate form data against event definition.
     *
     * @return array<string, string> field => error message
     */
    public function validate(string $eventType, EventDefinition $event, array $data): array
    {
        $errors = [];

        // 1. Required fields
        foreach ($event->requiredFields as $field) {
            $val = $data[$field] ?? null;
            if ($val === false || $val === null || $val === '') {
                $errors[$field] = 'Pflichtfeld';
            }
        }

        // 2. Declarative conditional rules from EventDefinition
        foreach ($event->conditionalRules as $targetField => $rule) {
            if ($this->evaluateCondition($rule['when'], $data) && empty($data[$targetField])) {
                $errors[$targetField] = $rule['then'];
            }
        }

        return $errors;
    }

    /**
     * Evaluate a conditional rule's "when" clause.
     *
     * @param array{field: string, operator: string, value: mixed} $when
     */
    private function evaluateCondition(array $when, array $data): bool
    {
        $fieldValue = $data[$when['field']] ?? null;
        $operator = $when['operator'];
        $compareValue = $when['value'];

        return match ($operator) {
            'empty' => empty($fieldValue),
            'not_empty' => !empty($fieldValue),
            'equals' => $fieldValue === $compareValue,
            'not_equals' => $fieldValue !== $compareValue,
            'not_in' => !in_array($fieldValue, (array) $compareValue, true),
            'in' => in_array($fieldValue, (array) $compareValue, true),
            default => false,
        };
    }
}
