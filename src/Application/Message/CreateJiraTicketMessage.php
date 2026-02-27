<?php

declare(strict_types=1);

namespace App\Application\Message;

use App\Domain\ValueObject\EventDefinition;

final readonly class CreateJiraTicketMessage
{
    public function __construct(
        public string $requestId,
        public string $eventType,
        public EventDefinition $eventMeta,
        public array $data,
    ) {
    }
}
