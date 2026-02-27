<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\ValueObject\EventDefinition;

/**
 * Creates tickets in an external issue tracker (e.g. Jira).
 */
interface JiraClientInterface
{
    /**
     * Create a ticket for the given event and data.
     *
     * @return string The ticket key (e.g. "ITOPS-42")
     *
     * @throws \RuntimeException on API or network errors
     */
    public function createTicket(EventDefinition $event, array $data, string $requestId): string;
}
