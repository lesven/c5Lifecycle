<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\EvidenceSubmission;
use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\Jira\JiraClient;

class CreateJiraTicketUseCase
{
    public function __construct(
        private readonly JiraClient $jiraClient,
        private readonly EvidenceConfig $config,
    ) {}

    /**
     * Create a Jira ticket if required/optional for this event type.
     *
     * @return string|null Ticket key or null if not applicable
     * @throws \RuntimeException if Jira ticket creation fails and rule is 'required'
     */
    public function execute(EvidenceSubmission $submission): ?string
    {
        $jiraRule = $this->config->getJiraRule($submission->eventType);

        if ($jiraRule === 'none') {
            return null;
        }

        try {
            return $this->jiraClient->createTicket(
                $submission->eventMeta,
                $submission->data,
                $submission->requestId
            );
        } catch (\Throwable $e) {
            if ($jiraRule === 'required') {
                throw $e;
            }
            // optional: swallow error
            return null;
        }
    }
}
