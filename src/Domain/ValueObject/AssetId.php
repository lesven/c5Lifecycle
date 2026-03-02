<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object for a C5 asset identifier (e.g. "SRV-12345", "WS-0042").
 *
 * Wrapping the raw string prevents accidental confusion with other identifiers
 * (e.g. RequestId) and gives the domain a named type for lookups and logging.
 */
final readonly class AssetId implements \Stringable
{
    public const UNKNOWN = 'UNKNOWN';

    private function __construct(public string $value)
    {
    }

    /**
     * Creates an AssetId from a raw string. Falls back to 'UNKNOWN' when the
     * value is null or empty (e.g. missing form field).
     */
    public static function from(?string $raw): self
    {
        return new self((isset($raw) && $raw !== '') ? $raw : self::UNKNOWN);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function isUnknown(): bool
    {
        return $this->value === self::UNKNOWN;
    }
}
