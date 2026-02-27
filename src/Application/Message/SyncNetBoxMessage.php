<?php

declare(strict_types=1);

namespace App\Application\Message;

use App\Domain\ValueObject\EventDefinition;

final readonly class SyncNetBoxMessage
{
    public function __construct(
        public string $requestId,
        public string $eventType,
        public EventDefinition $eventMeta,
        public array $data,
        public string $emailBody,
        public string $evidenceTo,
        public ?string $submittedBy = null,
    ) {
    }
}
