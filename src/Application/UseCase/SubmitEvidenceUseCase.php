<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\Step\CreateJiraStep;
use App\Application\UseCase\Step\PersistLogStep;
use App\Application\UseCase\Step\SendMailStep;
use App\Application\UseCase\Step\SyncNetBoxStep;
use App\Application\Validator\EventDataValidator;
use App\Domain\Repository\SubmissionLogRepositoryInterface;
use App\Domain\Service\EventRegistry;
use App\Domain\ValueObject\RequestId;
use Psr\Log\LoggerInterface;

final class SubmitEvidenceUseCase
{
    /** @var SubmissionStepInterface[] */
    private readonly array $steps;

    public function __construct(
        private readonly EventRegistry $eventRegistry,
        private readonly EventDataValidator $validator,
        private readonly SendEvidenceMailUseCase $sendMail,
        private readonly CreateJiraTicketUseCase $createJira,
        private readonly SyncNetBoxUseCase $syncNetBox,
        private readonly LoggerInterface $evidenceLogger,
        private readonly SubmissionLogRepositoryInterface $submissionLogRepository,
    ) {
        $this->steps = [
            new SendMailStep($this->sendMail),
            new CreateJiraStep($this->createJira),
            new SyncNetBoxStep($this->syncNetBox),
            new PersistLogStep($this->submissionLogRepository, $this->evidenceLogger),
        ];
    }

    public function execute(string $eventType, array $data, ?string $submittedBy = null): SubmissionResult
    {
        $result = new SubmissionResult();
        $requestId = RequestId::generate()->toString();
        $result->requestId = $requestId;
        $result->eventType = $eventType;

        $this->evidenceLogger->info('Submit request', ['request_id' => $requestId, 'event' => $eventType, 'submitted_by' => $submittedBy]);

        // 1. Validate event type
        if (!$this->eventRegistry->exists($eventType)) {
            $result->error = "Unbekannter Event-Typ: {$eventType}";
            $result->httpStatus = 404;
            return $result;
        }

        $event = $this->eventRegistry->get($eventType);
        // defensive: EventRegistry::get() can return null
        if ($event === null) {
            $this->evidenceLogger->error('Event metadata missing', ['request_id' => $requestId, 'event' => $eventType]);
            $result->error = 'Interner Fehler: Event-Metadaten nicht gefunden';
            $result->httpStatus = 500;
            return $result;
        }

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

        $submission = new EvidenceSubmission($eventType, $requestId, $event, $data, $submittedBy);

        // 3. Execute pipeline steps
        $context = [];
        foreach ($this->steps as $step) {
            try {
                $step->execute($submission, $result, $context);

                if ($step instanceof SendMailStep) {
                    $this->evidenceLogger->info('Mail sent', ['request_id' => $requestId]);
                }
                if ($step instanceof CreateJiraStep && $result->jiraTicket !== null) {
                    $this->evidenceLogger->info('Jira ticket created', ['request_id' => $requestId, 'ticket' => $result->jiraTicket]);
                }
            } catch (\Throwable $e) {
                $this->evidenceLogger->error($this->stepErrorKey($step) . ' failed', [
                    'request_id' => $requestId,
                    'error' => $e->getMessage(),
                ]);

                return $this->handleStepFailure($step, $e, $result);
            }
        }

        $result->success = true;

        return $result;
    }

    private function handleStepFailure(SubmissionStepInterface $step, \Throwable $e, SubmissionResult $result): SubmissionResult
    {
        if ($step instanceof SendMailStep) {
            $result->error = 'Evidence-Mail konnte nicht versendet werden: ' . $e->getMessage();
            $result->httpStatus = 502;
        } elseif ($step instanceof CreateJiraStep) {
            $result->error = 'Jira-Ticket konnte nicht erstellt werden (required)';
            $result->httpStatus = 502;
        } else {
            $result->error = 'Interner Fehler: ' . $e->getMessage();
            $result->httpStatus = 500;
        }

        return $result;
    }

    private function stepErrorKey(SubmissionStepInterface $step): string
    {
        return match (true) {
            $step instanceof SendMailStep => 'Mail',
            $step instanceof CreateJiraStep => 'Jira',
            $step instanceof SyncNetBoxStep => 'NetBox',
            $step instanceof PersistLogStep => 'Persistence',
            default => 'Step',
        };
    }
}
