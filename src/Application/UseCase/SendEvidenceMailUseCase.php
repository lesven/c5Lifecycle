<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\EvidenceSubmission;
use App\Domain\Service\EvidenceMailBuilder;
use App\Domain\Service\EventRegistry;
use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\Mail\EvidenceMailSender;

class SendEvidenceMailUseCase
{
    public function __construct(
        private readonly EvidenceMailBuilder $mailBuilder,
        private readonly EvidenceMailSender $mailSender,
        private readonly EventRegistry $eventRegistry,
        private readonly EvidenceConfig $config,
    ) {}

    /**
     * Build and send the evidence email.
     *
     * @return array{subject: string, body: string, recipients: array}
     */
    public function execute(EvidenceSubmission $submission): array
    {
        $subject = $this->eventRegistry->buildSubject($submission->eventType, $submission->assetId());
        $body = $this->mailBuilder->build($submission->eventMeta, $submission->data, $submission->requestId);
        $recipients = $this->config->getEvidenceRecipients($submission->track());

        $this->mailSender->send($recipients, $subject, $body, $submission->requestId);

        return [
            'subject' => $subject,
            'body' => $body,
            'recipients' => $recipients,
        ];
    }
}
