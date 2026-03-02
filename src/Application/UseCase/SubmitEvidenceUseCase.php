<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\Validator\EventDataValidator;
use App\Domain\Service\EventRegistry;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\LogContext;
use App\Domain\ValueObject\RequestId;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class SubmitEvidenceUseCase
{
    /** @var SubmissionStepInterface[] */
    private readonly array $steps;

    public function __construct(
        private readonly EventRegistry $eventRegistry,
        private readonly EventDataValidator $validator,
        private readonly LoggerInterface $evidenceLogger,
        #[TaggedIterator('c5.submission_step')]
        iterable $submissionSteps,
    ) {
        $this->steps = $submissionSteps instanceof \Traversable
            ? iterator_to_array($submissionSteps)
            : (array) $submissionSteps;
    }

    public function execute(string $eventType, array $data, ?string $submittedBy = null): SubmissionResult
    {
        $result = new SubmissionResult();
        $requestId = RequestId::generate()->toString();
        $result->requestId = $requestId;
        $result->eventType = $eventType;

        $this->evidenceLogger->info('Submit request', LogContext::for($requestId)
            ->withEvent($eventType)
            ->with('submitted_by', $submittedBy)
            ->toArray());

        // 1. Validate event type
        if (!$this->eventRegistry->exists($eventType)) {
            $result->error = "Unbekannter Event-Typ: {$eventType}";
            $result->httpStatus = 404;
            return $result;
        }

        $event = $this->eventRegistry->get($eventType);
        // defensive: EventRegistry::get() can return null
        if ($event === null) {
            $this->evidenceLogger->error('Event metadata missing', LogContext::for($requestId)->withEvent($eventType)->toArray());
            $result->error = 'Interner Fehler: Event-Metadaten nicht gefunden';
            $result->httpStatus = 500;
            return $result;
        }

        $result->assetId = AssetId::from($data['asset_id'] ?? null)->value;

        // 2. Validate data
        $errors = $this->validator->validate($eventType, $event, $data);
        if (!empty($errors)) {
            $this->evidenceLogger->warning('Validation failed', LogContext::for($requestId)->with('errors', $errors)->toArray());
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
                $this->evidenceLogger->info($step->getStepName() . ' completed', LogContext::for($requestId)->toArray());
            } catch (\Throwable $e) {
                $this->evidenceLogger->error($step->getStepName() . ' failed', LogContext::for($requestId)->withError($e)->toArray());
                $step->handleFailure($result, $e);
                return $result;
            }
        }

        $result->success = true;

        return $result;
    }
}
