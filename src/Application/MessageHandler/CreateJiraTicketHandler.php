<?php

declare(strict_types=1);

namespace App\Application\MessageHandler;

use App\Application\DTO\EvidenceSubmission;
use App\Application\Message\CreateJiraTicketMessage;
use App\Application\UseCase\CreateJiraTicketUseCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateJiraTicketHandler
{
    public function __construct(
        private readonly CreateJiraTicketUseCase $useCase,
        private readonly LoggerInterface $jiraLogger,
    ) {}

    public function __invoke(CreateJiraTicketMessage $message): void
    {
        $this->jiraLogger->info('Processing async Jira ticket creation', [
            'request_id' => $message->requestId,
            'event_type' => $message->eventType,
        ]);

        $submission = new EvidenceSubmission(
            $message->eventType,
            $message->requestId,
            $message->eventMeta,
            $message->data,
        );

        $ticket = $this->useCase->execute($submission);

        if ($ticket !== null) {
            $this->jiraLogger->info('Async Jira ticket created', [
                'request_id' => $message->requestId,
                'ticket' => $ticket,
            ]);
        }
    }
}
