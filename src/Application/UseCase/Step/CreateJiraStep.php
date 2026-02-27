<?php

declare(strict_types=1);

namespace App\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\CreateJiraTicketUseCase;
use App\Application\UseCase\SubmissionStepInterface;

/**
 * Pipeline step: Create Jira ticket (optional/required based on config).
 */
final class CreateJiraStep implements SubmissionStepInterface
{
    public function __construct(
        private readonly CreateJiraTicketUseCase $createJira,
    ) {
    }

    public function execute(EvidenceSubmission $submission, SubmissionResult $result, array &$context): void
    {
        $result->jiraTicket = $this->createJira->execute($submission);
    }
}
