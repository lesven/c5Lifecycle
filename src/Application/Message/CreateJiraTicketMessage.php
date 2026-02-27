<?php

declare(strict_types=1);

namespace App\Application\Message;

final readonly class CreateJiraTicketMessage
{
    public function __construct(
        public string $requestId,
        public string $eventType,
        public array $eventMeta,
        public array $data,
    ) {}
}
