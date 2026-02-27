<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\MessageHandler;

use App\Application\DTO\EvidenceSubmission;
use App\Application\Message\SyncNetBoxMessage;
use App\Application\MessageHandler\SyncNetBoxHandler;
use App\Application\UseCase\SyncNetBoxUseCase;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SyncNetBoxHandlerTest extends TestCase
{
    public function testHandlerCallsUseCaseWithCorrectArguments(): void
    {
        $message = new SyncNetBoxMessage(
            requestId: 'req-789',
            eventType: 'rz_provision',
            eventMeta: ['label' => 'RZ-Bereitstellung', 'track' => 'rz_assets'],
            data: ['asset_id' => 'SRV-003', 'device_type' => 'Server'],
            emailBody: 'Evidence mail body content',
            evidenceTo: 'evidence@example.com',
        );

        $useCase = $this->createMock(SyncNetBoxUseCase::class);
        $useCase->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (EvidenceSubmission $sub) {
                    return $sub->eventType === 'rz_provision'
                        && $sub->requestId === 'req-789'
                        && $sub->data['asset_id'] === 'SRV-003';
                }),
                'Evidence mail body content',
                'evidence@example.com'
            )
            ->willReturn(['synced' => true, 'status' => 'active', 'error' => null, 'error_trace' => null]);

        $handler = new SyncNetBoxHandler($useCase, new NullLogger());
        $handler($message);
    }

    public function testHandlerLogsErrorResult(): void
    {
        $message = new SyncNetBoxMessage(
            requestId: 'req-err',
            eventType: 'rz_retire',
            eventMeta: ['label' => 'RZ-Außerbetriebnahme', 'track' => 'rz_assets'],
            data: ['asset_id' => 'SRV-004'],
            emailBody: 'body',
            evidenceTo: 'to@test.de',
        );

        $useCase = $this->createMock(SyncNetBoxUseCase::class);
        $useCase->expects($this->once())
            ->method('execute')
            ->willReturn(['synced' => false, 'status' => null, 'error' => 'Connection failed', 'error_trace' => null]);

        $handler = new SyncNetBoxHandler($useCase, new NullLogger());
        $handler($message);
    }
}
