<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\UseCase\CreateJiraTicketUseCase;
use App\Application\UseCase\SendEvidenceMailUseCase;
use App\Application\UseCase\Step\CreateJiraStep;
use App\Application\UseCase\Step\PersistLogStep;
use App\Application\UseCase\Step\SendMailStep;
use App\Application\UseCase\Step\SyncNetBoxStep;
use App\Application\UseCase\SubmitEvidenceUseCase;
use App\Application\UseCase\SyncNetBoxUseCase;
use App\Application\Validator\EventDataValidator;
use App\Domain\Repository\SubmissionLogRepositoryInterface;
use App\Tests\EventRegistryFixture;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class SubmitEvidenceUseCaseTest extends TestCase
{
    private function createUseCase(
        ?SendEvidenceMailUseCase $sendMail = null,
        ?CreateJiraTicketUseCase $createJira = null,
        ?SyncNetBoxUseCase $syncNetBox = null,
        ?SubmissionLogRepositoryInterface $submissionLogRepository = null,
    ): SubmitEvidenceUseCase {
        $sendMail ??= $this->createMock(SendEvidenceMailUseCase::class);
        $createJira ??= $this->createMock(CreateJiraTicketUseCase::class);
        $syncNetBox ??= $this->createMock(SyncNetBoxUseCase::class);
        $submissionLogRepository ??= $this->createMock(SubmissionLogRepositoryInterface::class);

        if ($sendMail instanceof MockObject) {
            $sendMail->method('execute')->willReturn([
                'subject' => 'Test Subject',
                'body' => 'Test Body',
                'recipients' => ['to' => 'test@example.com', 'cc' => []],
            ]);
        }
        if ($syncNetBox instanceof MockObject) {
            $syncNetBox->method('execute')->willReturn([
                'synced' => false, 'status' => null, 'error' => null, 'error_trace' => null,
            ]);
        }

        $steps = [
            new SendMailStep($sendMail),
            new CreateJiraStep($createJira),
            new SyncNetBoxStep($syncNetBox),
            new PersistLogStep($submissionLogRepository, new NullLogger()),
        ];

        return new SubmitEvidenceUseCase(
            EventRegistryFixture::create(),
            new EventDataValidator(),
            new NullLogger(),
            $steps,
        );
    }

    public function testHappyPathReturnsSuccess(): void
    {
        $useCase = $this->createUseCase();
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'nutzungstyp' => 'Produktiv',
            'manufacturer' => 'Dell',
            'model' => 'R740',
            'serial_number' => 'ABC',
            'region_id' => '1',
            'site_group_id' => '2',
            'site_id' => '3',
            'commission_date' => '2024-01-15',
            'asset_owner' => 'Owner',
            'service' => 'SVC',
            'criticality' => 'Hoch',
            'change_ref' => 'CHG-001',
            'monitoring_active' => true,
            'patch_process' => true,
            'access_controlled' => true,
        ];

        $result = $useCase->execute('rz_provision', $data);

        $this->assertTrue($result->success);
        $this->assertTrue($result->mailSent);
        $this->assertEquals('SRV-001', $result->assetId);
        $this->assertEquals('rz_provision', $result->eventType);
        $this->assertNotEmpty($result->requestId);
        $this->assertEquals(200, $result->httpStatus);
    }

    public function testUnknownEventTypeReturns404(): void
    {
        $useCase = $this->createUseCase();
        $result = $useCase->execute('nonexistent_event', []);

        $this->assertFalse($result->success);
        $this->assertEquals(404, $result->httpStatus);
        $this->assertStringContainsString('Unbekannter Event-Typ', $result->error);
    }

    public function testValidationFailureReturns422(): void
    {
        $useCase = $this->createUseCase();
        $result = $useCase->execute('rz_provision', ['asset_id' => 'SRV-001']);

        $this->assertFalse($result->success);
        $this->assertEquals(422, $result->httpStatus);
        $this->assertNotNull($result->validationErrors);
        $this->assertArrayHasKey('device_type', $result->validationErrors);
    }

    public function testMailFailureReturns502(): void
    {
        $sendMail = $this->createMock(SendEvidenceMailUseCase::class);
        $sendMail->method('execute')->willThrowException(new RuntimeException('SMTP error'));

        $useCase = $this->createUseCase(sendMail: $sendMail);
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'nutzungstyp' => 'Produktiv',
            'manufacturer' => 'Dell',
            'model' => 'R740',
            'serial_number' => 'ABC',
            'region_id' => '1',
            'site_group_id' => '2',
            'site_id' => '3',
            'commission_date' => '2024-01-15',
            'asset_owner' => 'Owner',
            'service' => 'SVC',
            'criticality' => 'Hoch',
            'change_ref' => 'CHG-001',
            'monitoring_active' => true,
            'patch_process' => true,
            'access_controlled' => true,
        ];

        $result = $useCase->execute('rz_provision', $data);

        $this->assertFalse($result->success);
        $this->assertEquals(502, $result->httpStatus);
        $this->assertStringContainsString('Evidence-Mail', $result->error);
    }

    public function testJiraFailureOnRequiredReturns502(): void
    {
        $createJira = $this->createMock(CreateJiraTicketUseCase::class);
        $createJira->method('execute')->willThrowException(new RuntimeException('Jira error'));

        $useCase = $this->createUseCase(createJira: $createJira);
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'nutzungstyp' => 'Produktiv',
            'manufacturer' => 'Dell',
            'model' => 'R740',
            'serial_number' => 'ABC',
            'region_id' => '1',
            'site_group_id' => '2',
            'site_id' => '3',
            'commission_date' => '2024-01-15',
            'asset_owner' => 'Owner',
            'service' => 'SVC',
            'criticality' => 'Hoch',
            'change_ref' => 'CHG-001',
            'monitoring_active' => true,
            'patch_process' => true,
            'access_controlled' => true,
        ];

        $result = $useCase->execute('rz_provision', $data);

        $this->assertFalse($result->success);
        $this->assertEquals(502, $result->httpStatus);
        $this->assertStringContainsString('Jira', $result->error);
    }

    public function testRequestIdIsUuidFormat(): void
    {
        $useCase = $this->createUseCase();
        $result = $useCase->execute('nonexistent_event', []);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $result->requestId
        );
    }
}
