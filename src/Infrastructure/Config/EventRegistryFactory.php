<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use App\Domain\Service\EventRegistry;

/**
 * Factory that creates an EventRegistry from a YAML event definitions file.
 */
final class EventRegistryFactory
{
    public static function create(string $path): EventRegistry
    {
        $definitions = EventDefinitionLoader::load($path);

        return new EventRegistry($definitions);
    }
}
