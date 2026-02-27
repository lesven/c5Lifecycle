<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\ValueObject\EventDefinition;
use App\Domain\ValueObject\EventType;

final class EventRegistry
{
    /** @var array<string, EventDefinition> */
    private array $definitions;

    /**
     * @param array<string, EventDefinition> $definitions Pre-loaded event definitions
     */
    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;
    }

    public function get(string $eventType): ?EventDefinition
    {
        return $this->definitions[$eventType] ?? null;
    }

    public function getByEnum(EventType $eventType): EventDefinition
    {
        return $this->definitions[$eventType->value];
    }

    public function exists(string $eventType): bool
    {
        return isset($this->definitions[$eventType]);
    }

    public function buildSubject(string $eventType, string $assetId): string
    {
        $event = $this->definitions[$eventType];

        return sprintf('[C5 Evidence] %s - %s - %s', $event->category, $event->subjectType, $assetId);
    }

    /** @return array<string, EventDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }
}
