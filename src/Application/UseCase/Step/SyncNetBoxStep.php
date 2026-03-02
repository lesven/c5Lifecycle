<?php

declare(strict_types=1);

namespace App\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\SubmissionStepInterface;
use App\Application\UseCase\SyncNetBoxUseCase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step: Sync device state to NetBox.
 */
#[AutoconfigureTag('c5.submission_step', ['priority' => 25])]
final class SyncNetBoxStep implements SubmissionStepInterface
{
    public function __construct(
        private readonly SyncNetBoxUseCase $syncNetBox,
    ) {
    }

    public function execute(EvidenceSubmission $submission, SubmissionResult $result, array &$context): void
    {
        $emailBody = $context['mail_body'] ?? '';
        $evidenceTo = $context['mail_recipients_to'] ?? '';

        $netboxResult = $this->syncNetBox->execute($submission, $emailBody, $evidenceTo);
        $result->netboxSynced = $netboxResult['synced'];
        $result->netboxStatus = $netboxResult['status'];
        $result->netboxError = $netboxResult['error'];
        $result->netboxErrorTrace = $netboxResult['error_trace'];
    }

    public function getStepName(): string
    {
        return 'NetBox';
    }

    public function handleFailure(SubmissionResult $result, \Throwable $e): void
    {
        $result->error = 'Interner Fehler: ' . $e->getMessage();
        $result->httpStatus = 500;
    }
}
