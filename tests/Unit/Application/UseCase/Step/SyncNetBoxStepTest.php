<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\Step\SyncNetBoxStep;
use App\Application\UseCase\SyncNetBoxUseCase;
use App\Domain\ValueObject\EventDefinition;
use PHPUnit\Framework\TestCase;

class SyncNetBoxStepTest extends TestCase
{
    private function createSubmission(): EvidenceSubmission
    {
        return new EvidenceSubmission(
            eventType: 'rz_provision',
            requestId: 'req-789',
            eventMeta: new EventDefinition(
                track: 'rz_assets',
                label: 'Test',
                category: 'RZ',
                subjectType: 'Test',
                requiredFields: [],
            ),
            data: ['asset_id' => 'SRV-001'],
        );
    }

    public function testExecuteSetsNetboxResultFromContext(): void
    {
        $syncNetBox = $this->createMock(SyncNetBoxUseCase::class);
        $syncNetBox->expects($this->once())
            ->method('execute')
            ->with(
                $this->anything(),
                'Mail body from context',
                'to@example.com',
            )
            ->willReturn([
                'synced' => true,
                'status' => 'active',
                'error' => null,
                'error_trace' => null,
            ]);

        $step = new SyncNetBoxStep($syncNetBox);
        $result = new SubmissionResult();
        $context = [
            'mail_body' => 'Mail body from context',
            'mail_recipients_to' => 'to@example.com',
        ];

        $step->execute($this->createSubmission(), $result, $context);

        $this->assertTrue($result->netboxSynced);
        $this->assertSame('active', $result->netboxStatus);
        $this->assertNull($result->netboxError);
    }

    public function testExecuteHandlesErrorResult(): void
    {
        $syncNetBox = $this->createMock(SyncNetBoxUseCase::class);
        $syncNetBox->method('execute')
            ->willReturn([
                'synced' => false,
                'status' => null,
                'error' => 'Connection refused',
                'error_trace' => 'trace...',
            ]);

        $step = new SyncNetBoxStep($syncNetBox);
        $result = new SubmissionResult();
        $context = ['mail_body' => '', 'mail_recipients_to' => ''];

        $step->execute($this->createSubmission(), $result, $context);

        $this->assertFalse($result->netboxSynced);
        $this->assertSame('Connection refused', $result->netboxError);
    }

    public function testExecuteUsesEmptyStringWhenContextMissing(): void
    {
        $syncNetBox = $this->createMock(SyncNetBoxUseCase::class);
        $syncNetBox->expects($this->once())
            ->method('execute')
            ->with($this->anything(), '', '')
            ->willReturn([
                'synced' => false,
                'status' => null,
                'error' => null,
                'error_trace' => null,
            ]);

        $step = new SyncNetBoxStep($syncNetBox);
        $result = new SubmissionResult();
        $context = [];

        $step->execute($this->createSubmission(), $result, $context);
    }
}
