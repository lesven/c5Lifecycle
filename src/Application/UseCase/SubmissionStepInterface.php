<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;

/**
 * Pipeline step interface for the evidence submission workflow.
 *
 * Each step performs one aspect of the submission (mail, jira, netbox, persistence).
 * Steps are executed sequentially by SubmitEvidenceUseCase.
 */
interface SubmissionStepInterface
{
    /**
     * Execute this pipeline step.
     *
     * @param EvidenceSubmission $submission The validated submission
     * @param SubmissionResult $result  Mutable result object to populate
     * @param array<string, mixed> $context Shared context between steps (e.g., mail body for NetBox)
     *
     * @throws \Throwable if the step fails critically (e.g., mail delivery failure)
     */
    public function execute(EvidenceSubmission $submission, SubmissionResult $result, array &$context): void;
}
