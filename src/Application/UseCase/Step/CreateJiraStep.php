<?php

declare(strict_types=1);

namespace App\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\CreateJiraTicketUseCase;
use App\Application\UseCase\SubmissionStepInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step: Create Jira ticket (optional/required based on config).
 */
#[AutoconfigureTag('c5.submission_step', ['priority' => 50])]
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

    public function getStepName(): string
    {
        return 'Jira';
    }

    public function handleFailure(SubmissionResult $result, \Throwable $e): void
    {
        $result->error = 'Jira-Ticket konnte nicht erstellt werden (required)';
        $result->httpStatus = 502;
    }
}
