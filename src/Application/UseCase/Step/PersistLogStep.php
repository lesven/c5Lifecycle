<?php

declare(strict_types=1);

namespace App\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\SubmissionStepInterface;
use App\Domain\Repository\SubmissionLogRepositoryInterface;
use App\Infrastructure\Persistence\Entity\SubmissionLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step: Persist submission log to database.
 *
 * This step is non-critical — failures are logged but don't abort the submission.
 */
#[AutoconfigureTag('c5.submission_step', ['priority' => 10])]
final class PersistLogStep implements SubmissionStepInterface
{
    public function __construct(
        private readonly SubmissionLogRepositoryInterface $submissionLogRepository,
        private readonly LoggerInterface $evidenceLogger,
    ) {
    }

    public function execute(EvidenceSubmission $submission, SubmissionResult $result, array &$context): void
    {
        try {
            $log = new SubmissionLog(
                $submission->requestId,
                $submission->eventType,
                $submission->assetId()->value,
                $submission->data,
            );
            $log->setMailSent(true);
            $log->setJiraTicket($result->jiraTicket);
            $log->setNetboxSynced($result->netboxSynced);
            $log->setSubmittedBy($submission->submittedBy);
            $this->submissionLogRepository->save($log);
        } catch (\Throwable $e) {
            $this->evidenceLogger->warning('SubmissionLog speichern fehlgeschlagen', [
                'request_id' => $submission->requestId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getStepName(): string
    {
        return 'Persistence';
    }

    public function handleFailure(SubmissionResult $result, \Throwable $e): void
    {
        // PersistLogStep catches all errors internally; this is a safety fallback only.
        $result->error = 'Interner Fehler: ' . $e->getMessage();
        $result->httpStatus = 500;
    }
}
