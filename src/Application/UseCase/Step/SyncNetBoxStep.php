<?php

declare(strict_types=1);

namespace App\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\SubmissionStepInterface;
use App\Application\UseCase\SyncNetBoxUseCase;

/**
 * Pipeline step: Sync device state to NetBox.
 */
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
}
