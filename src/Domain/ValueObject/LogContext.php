<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Immutable value object that builds a structured log context array.
 *
 * Enforces consistent key names across all log calls in the application.
 *
 * Usage:
 *   $ctx = LogContext::for($requestId)->withEvent($eventType);
 *   $logger->info('Submit request', $ctx->toArray());
 */
final class LogContext
{
    /** @param array<string, mixed> $data */
    private function __construct(
        private readonly array $data,
    ) {
    }

    /** Create a base context with just the request ID. */
    public static function for(string $requestId): self
    {
        return new self(['request_id' => $requestId]);
    }

    public function withEvent(string $eventType): self
    {
        return new self($this->data + ['event' => $eventType]);
    }

    public function withAsset(AssetId|string $assetId): self
    {
        return new self($this->data + ['asset_id' => (string) $assetId]);
    }

    public function withError(\Throwable $e): self
    {
        return new self($this->data + [
            'exception' => $e::class,
            'error' => $e->getMessage(),
        ]);
    }

    /** Add any additional key-value pair. */
    public function with(string $key, mixed $value): self
    {
        return new self($this->data + [$key => $value]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
