<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\Step\PersistLogStep;
use App\Domain\Repository\SubmissionLogRepositoryInterface;
use App\Domain\ValueObject\EventDefinition;
use App\Infrastructure\Persistence\Entity\SubmissionLog;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PersistLogStepTest extends TestCase
{
    private function createSubmission(): EvidenceSubmission
    {
        return new EvidenceSubmission(
            eventType: 'rz_provision',
            requestId: 'req-log',
            eventMeta: new EventDefinition(
                track: 'rz_assets',
                label: 'Test',
                category: 'RZ',
                subjectType: 'Test',
                requiredFields: [],
            ),
            data: ['asset_id' => 'SRV-001'],
            submittedBy: 'Test User (test@test.de)',
        );
    }

    public function testExecuteSavesSubmissionLog(): void
    {
        $repo = $this->createMock(SubmissionLogRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (SubmissionLog $log) {
                return $log->getRequestId() === 'req-log'
                    && $log->getEventType() === 'rz_provision'
                    && $log->getAssetId() === 'SRV-001';
            }));

        $step = new PersistLogStep($repo, new NullLogger());
        $result = new SubmissionResult();
        $result->jiraTicket = 'ITOPS-99';
        $result->netboxSynced = true;
        $context = [];

        $step->execute($this->createSubmission(), $result, $context);
    }

    public function testExecuteDoesNotThrowOnRepositoryFailure(): void
    {
        $repo = $this->createMock(SubmissionLogRepositoryInterface::class);
        $repo->method('save')
            ->willThrowException(new \RuntimeException('DB connection lost'));

        $step = new PersistLogStep($repo, new NullLogger());
        $result = new SubmissionResult();
        $context = [];

        // Should not throw — failures are logged but swallowed
        $step->execute($this->createSubmission(), $result, $context);
        $this->assertTrue(true); // Reached here = no exception
    }
}
