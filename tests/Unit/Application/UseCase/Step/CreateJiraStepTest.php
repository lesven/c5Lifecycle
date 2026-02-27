<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\CreateJiraTicketUseCase;
use App\Application\UseCase\Step\CreateJiraStep;
use App\Domain\ValueObject\EventDefinition;
use PHPUnit\Framework\TestCase;

class CreateJiraStepTest extends TestCase
{
    private function createSubmission(): EvidenceSubmission
    {
        return new EvidenceSubmission(
            eventType: 'admin_return',
            requestId: 'req-456',
            eventMeta: new EventDefinition(
                track: 'admin_devices',
                label: 'Rückgabe',
                category: 'ADM',
                subjectType: 'Rückgabe',
                requiredFields: [],
            ),
            data: ['asset_id' => 'WS-001'],
        );
    }

    public function testExecuteSetsJiraTicket(): void
    {
        $createJira = $this->createMock(CreateJiraTicketUseCase::class);
        $createJira->expects($this->once())
            ->method('execute')
            ->willReturn('ITOPS-42');

        $step = new CreateJiraStep($createJira);
        $result = new SubmissionResult();
        $context = [];

        $step->execute($this->createSubmission(), $result, $context);

        $this->assertSame('ITOPS-42', $result->jiraTicket);
    }

    public function testExecuteSetsNullWhenNotApplicable(): void
    {
        $createJira = $this->createMock(CreateJiraTicketUseCase::class);
        $createJira->method('execute')->willReturn(null);

        $step = new CreateJiraStep($createJira);
        $result = new SubmissionResult();
        $context = [];

        $step->execute($this->createSubmission(), $result, $context);

        $this->assertNull($result->jiraTicket);
    }

    public function testExecuteThrowsOnRequiredJiraFailure(): void
    {
        $createJira = $this->createMock(CreateJiraTicketUseCase::class);
        $createJira->method('execute')
            ->willThrowException(new \RuntimeException('Jira API error'));

        $step = new CreateJiraStep($createJira);
        $result = new SubmissionResult();
        $context = [];

        $this->expectException(\RuntimeException::class);
        $step->execute($this->createSubmission(), $result, $context);
    }
}
