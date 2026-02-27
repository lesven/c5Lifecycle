<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use App\Domain\ValueObject\EventDefinition;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads event definitions from a YAML configuration file.
 *
 * Converts declarative YAML event definitions into EventDefinition value objects
 * including conditional validation rules.
 */
final class EventDefinitionLoader
{
    /**
     * @param string $path Absolute path to the YAML file
     * @return array<string, EventDefinition> Keyed by event type slug
     */
    public static function load(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Event definitions file not found: {$path}");
        }

        $raw = Yaml::parseFile($path);
        if (!is_array($raw) || !isset($raw['events']) || !is_array($raw['events'])) {
            throw new RuntimeException('Invalid event definitions format: missing "events" key');
        }

        $definitions = [];
        foreach ($raw['events'] as $eventType => $config) {
            if (!is_array($config)) {
                throw new RuntimeException("Invalid config for event type: {$eventType}");
            }

            $conditionalRules = self::parseConditionalRules($config['conditional_rules'] ?? []);

            $definitions[$eventType] = new EventDefinition(
                track: $config['track'] ?? '',
                label: $config['label'] ?? '',
                category: $config['category'] ?? '',
                subjectType: $config['subject_type'] ?? '',
                requiredFields: $config['required_fields'] ?? [],
                conditionalRules: $conditionalRules,
            );
        }

        return $definitions;
    }

    /**
     * @return array<string, array{when: array{field: string, operator: string, value: mixed}, then: string}>
     */
    private static function parseConditionalRules(array $rawRules): array
    {
        $rules = [];
        foreach ($rawRules as $targetField => $rule) {
            if (!is_array($rule) || !isset($rule['when'], $rule['then'])) {
                continue;
            }

            $when = $rule['when'];
            $rules[$targetField] = [
                'when' => [
                    'field' => $when['field'] ?? '',
                    'operator' => $when['operator'] ?? 'empty',
                    'value' => $when['value'] ?? null,
                ],
                'then' => $rule['then'],
            ];
        }

        return $rules;
    }
}
