<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class EvidenceSubmission
{
    public function __construct(
        public string $eventType,
        public string $requestId,
        public array $eventMeta,
        public array $data,
    ) {
        // Convenience accessor
    }

    public function assetId(): string
    {
        return $this->data['asset_id'] ?? 'UNKNOWN';
    }

    public function track(): string
    {
        return $this->eventMeta['track'];
    }
}
