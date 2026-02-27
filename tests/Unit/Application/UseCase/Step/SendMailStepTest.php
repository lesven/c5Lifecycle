<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Step;

use App\Application\DTO\EvidenceSubmission;
use App\Application\DTO\SubmissionResult;
use App\Application\UseCase\SendEvidenceMailUseCase;
use App\Application\UseCase\Step\SendMailStep;
use App\Domain\ValueObject\EventDefinition;
use PHPUnit\Framework\TestCase;

class SendMailStepTest extends TestCase
{
    private function createSubmission(): EvidenceSubmission
    {
        return new EvidenceSubmission(
            eventType: 'rz_provision',
            requestId: 'req-123',
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

    public function testExecuteSetsMailSentAndContext(): void
    {
        $sendMail = $this->createMock(SendEvidenceMailUseCase::class);
        $sendMail->expects($this->once())
            ->method('execute')
            ->willReturn([
                'subject' => 'Test Subject',
                'body' => 'Mail body text',
                'recipients' => ['to' => 'test@example.com', 'cc' => []],
            ]);

        $step = new SendMailStep($sendMail);
        $result = new SubmissionResult();
        $context = [];

        $step->execute($this->createSubmission(), $result, $context);

        $this->assertTrue($result->mailSent);
        $this->assertSame('Mail body text', $context['mail_body']);
        $this->assertSame('test@example.com', $context['mail_recipients_to']);
    }

    public function testExecuteThrowsOnMailFailure(): void
    {
        $sendMail = $this->createMock(SendEvidenceMailUseCase::class);
        $sendMail->method('execute')
            ->willThrowException(new \RuntimeException('SMTP error'));

        $step = new SendMailStep($sendMail);
        $result = new SubmissionResult();
        $context = [];

        $this->expectException(\RuntimeException::class);
        $step->execute($this->createSubmission(), $result, $context);
    }
}
