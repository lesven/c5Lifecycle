<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\MessageHandler;

use App\Application\DTO\EvidenceSubmission;
use App\Application\Message\CreateJiraTicketMessage;
use App\Application\MessageHandler\CreateJiraTicketHandler;
use App\Application\UseCase\CreateJiraTicketUseCase;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CreateJiraTicketHandlerTest extends TestCase
{
    public function testHandlerCallsUseCaseWithCorrectSubmission(): void
    {
        $message = new CreateJiraTicketMessage(
            requestId: 'req-123',
            eventType: 'rz_provision',
            eventMeta: ['label' => 'RZ-Bereitstellung', 'track' => 'rz_assets'],
            data: ['asset_id' => 'SRV-001', 'device_type' => 'Server'],
        );

        $useCase = $this->createMock(CreateJiraTicketUseCase::class);
        $useCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (EvidenceSubmission $sub) {
                return $sub->eventType === 'rz_provision'
                    && $sub->requestId === 'req-123'
                    && $sub->data['asset_id'] === 'SRV-001';
            }))
            ->willReturn('PROJ-42');

        $handler = new CreateJiraTicketHandler($useCase, new NullLogger());
        $handler($message);
    }

    public function testHandlerHandlesNullTicketResult(): void
    {
        $message = new CreateJiraTicketMessage(
            requestId: 'req-456',
            eventType: 'rz_owner_confirm',
            eventMeta: ['label' => 'RZ-Eigentümerbestätigung', 'track' => 'rz_assets'],
            data: ['asset_id' => 'SRV-002'],
        );

        $useCase = $this->createMock(CreateJiraTicketUseCase::class);
        $useCase->expects($this->once())
            ->method('execute')
            ->willReturn(null);

        $handler = new CreateJiraTicketHandler($useCase, new NullLogger());
        $handler($message);
    }
}
