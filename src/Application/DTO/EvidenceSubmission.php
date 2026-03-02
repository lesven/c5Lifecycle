<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\EventDefinition;

final readonly class EvidenceSubmission
{
    public function __construct(
        public string $eventType,
        public string $requestId,
        public EventDefinition $eventMeta,
        public array $data,
        public ?string $submittedBy = null,
    ) {
    }

    public function assetId(): AssetId
    {
        return AssetId::from($this->data['asset_id'] ?? null);
    }

    public function track(): string
    {
        return $this->eventMeta->track;
    }
}
