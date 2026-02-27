<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Typed wrapper around raw form submission data.
 *
 * Provides safe, typed accessors and eliminates mixed-array coupling
 * at Domain/Application boundaries.
 */
final readonly class FormData
{
    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        private array $fields,
    ) {
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->fields[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function getBool(string $key): bool
    {
        return !empty($this->fields[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->fields[$key]) && $this->fields[$key] !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->fields;
    }
}
