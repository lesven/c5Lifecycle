<?php

declare(strict_types=1);

namespace App\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\SendEvidenceMailUseCase;
use App\Application\UseCase\SubmissionStepInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pipeline step: Send evidence email (mandatory, synchronous).
 *
 * This is the critical first step — submission fails if mail delivery fails.
 */
#[AutoconfigureTag('c5.submission_step', ['priority' => 100])]
final class SendMailStep implements SubmissionStepInterface
{
    public function __construct(
        private readonly SendEvidenceMailUseCase $sendMail,
    ) {
    }

    public function execute(EvidenceSubmission $submission, SubmissionResult $result, array &$context): void
    {
        $mailResult = $this->sendMail->execute($submission);
        $result->mailSent = true;
        $context['mail_body'] = $mailResult['body'];
        $context['mail_recipients_to'] = $mailResult['recipients']['to'];
    }

    public function getStepName(): string
    {
        return 'Mail';
    }

    public function handleFailure(SubmissionResult $result, \Throwable $e): void
    {
        $result->error = 'Evidence-Mail konnte nicht versendet werden: ' . $e->getMessage();
        $result->httpStatus = 502;
    }
}
