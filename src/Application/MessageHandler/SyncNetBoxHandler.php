<?php

declare(strict_types=1);

namespace App\Application\MessageHandler;

use App\Application\DTO\EvidenceSubmission;
use App\Application\Message\SyncNetBoxMessage;
use App\Application\UseCase\SyncNetBoxUseCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncNetBoxHandler
{
    public function __construct(
        private readonly SyncNetBoxUseCase $useCase,
        private readonly LoggerInterface $netboxLogger,
    ) {}

    public function __invoke(SyncNetBoxMessage $message): void
    {
        $this->netboxLogger->info('Processing async NetBox sync', [
            'request_id' => $message->requestId,
            'event_type' => $message->eventType,
        ]);

        $submission = new EvidenceSubmission(
            $message->eventType,
            $message->requestId,
            $message->eventMeta,
            $message->data,
        );

        $result = $this->useCase->execute($submission, $message->emailBody, $message->evidenceTo);

        $this->netboxLogger->info('Async NetBox sync completed', [
            'request_id' => $message->requestId,
            'synced' => $result['synced'],
            'status' => $result['status'],
            'error' => $result['error'],
        ]);
    }
}
