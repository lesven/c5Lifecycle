<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\Validator\EventDataValidator;
use App\Domain\Service\EventRegistry;
use App\Domain\ValueObject\RequestId;
use Psr\Log\LoggerInterface;

final class SubmitEvidenceUseCase
{
    public function __construct(
        private readonly EventRegistry $eventRegistry,
        private readonly EventDataValidator $validator,
        private readonly SendEvidenceMailUseCase $sendMail,
        private readonly CreateJiraTicketUseCase $createJira,
        private readonly SyncNetBoxUseCase $syncNetBox,
        private readonly LoggerInterface $evidenceLogger,
    ) {}

    public function execute(string $eventType, array $data): SubmissionResult
    {
        $result = new SubmissionResult();
        $requestId = RequestId::generate()->toString();
        $result->requestId = $requestId;
        $result->eventType = $eventType;

        $this->evidenceLogger->info('Submit request', ['request_id' => $requestId, 'event' => $eventType]);

        // 1. Validate event type
        if (!$this->eventRegistry->exists($eventType)) {
            $result->error = "Unbekannter Event-Typ: {$eventType}";
            $result->httpStatus = 404;
            return $result;
        }

        $event = $this->eventRegistry->get($eventType);
        $result->assetId = $data['asset_id'] ?? 'UNKNOWN';

        // 2. Validate data
        $errors = $this->validator->validate($eventType, $event, $data);
        if (!empty($errors)) {
            $this->evidenceLogger->warning('Validation failed', ['request_id' => $requestId, 'errors' => $errors]);
            $result->error = 'Validation failed';
            $result->validationErrors = $errors;
            $result->httpStatus = 422;
            return $result;
        }

        $submission = new EvidenceSubmission($eventType, $requestId, $event, $data);

        // 3. Send evidence email (mandatory, synchronous)
        try {
            $mailResult = $this->sendMail->execute($submission);
            $result->mailSent = true;
            $this->evidenceLogger->info('Mail sent', ['request_id' => $requestId]);
        } catch (\Throwable $e) {
            $this->evidenceLogger->error('Mail failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            $result->error = 'Evidence-Mail konnte nicht versendet werden: ' . $e->getMessage();
            $result->httpStatus = 502;
            return $result;
        }

        // 4. Create Jira ticket
        try {
            $result->jiraTicket = $this->createJira->execute($submission);
            if ($result->jiraTicket !== null) {
                $this->evidenceLogger->info('Jira ticket created', ['request_id' => $requestId, 'ticket' => $result->jiraTicket]);
            }
        } catch (\Throwable $e) {
            $this->evidenceLogger->error('Jira failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            $result->error = 'Jira-Ticket konnte nicht erstellt werden (required)';
            $result->httpStatus = 502;
            return $result;
        }

        // 5. Sync NetBox
        $netboxResult = $this->syncNetBox->execute($submission, $mailResult['body'], $mailResult['recipients']['to']);
        $result->netboxSynced = $netboxResult['synced'];
        $result->netboxStatus = $netboxResult['status'];
        $result->netboxError = $netboxResult['error'];
        $result->netboxErrorTrace = $netboxResult['error_trace'];

        $result->success = true;
        return $result;
    }
}
