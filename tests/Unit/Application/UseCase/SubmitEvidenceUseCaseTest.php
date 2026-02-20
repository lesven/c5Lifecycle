<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\UseCase\CreateJiraTicketUseCase;
use App\Application\UseCase\SendEvidenceMailUseCase;
use App\Application\UseCase\SubmitEvidenceUseCase;
use App\Application\UseCase\SyncNetBoxUseCase;
use App\Application\Validator\EventDataValidator;
use App\Domain\Service\EventRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SubmitEvidenceUseCaseTest extends TestCase
{
    private function createUseCase(
        ?SendEvidenceMailUseCase $sendMail = null,
        ?CreateJiraTicketUseCase $createJira = null,
        ?SyncNetBoxUseCase $syncNetBox = null,
    ): SubmitEvidenceUseCase {
        $sendMail ??= $this->createMock(SendEvidenceMailUseCase::class);
        $createJira ??= $this->createMock(CreateJiraTicketUseCase::class);
        $syncNetBox ??= $this->createMock(SyncNetBoxUseCase::class);

        // Configure default mock returns
        if ($sendMail instanceof \PHPUnit\Framework\MockObject\MockObject) {
            $sendMail->method('execute')->willReturn([
                'subject' => 'Test Subject',
                'body' => 'Test Body',
                'recipients' => ['to' => 'test@example.com', 'cc' => []],
            ]);
        }
        if ($syncNetBox instanceof \PHPUnit\Framework\MockObject\MockObject) {
            $syncNetBox->method('execute')->willReturn([
                'synced' => false, 'status' => null, 'error' => null, 'error_trace' => null,
            ]);
        }

        return new SubmitEvidenceUseCase(
            new EventRegistry(),
            new EventDataValidator(),
            $sendMail,
            $createJira,
            $syncNetBox,
            new NullLogger(),
        );
    }

    public function testHappyPathReturnsSuccess(): void
    {
        $useCase = $this->createUseCase();
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'manufacturer' => 'Dell',
            'model' => 'R740',
            'serial_number' => 'ABC',
            'location' => 'DC-1',
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
        $sendMail->method('execute')->willThrowException(new \RuntimeException('SMTP error'));

        $useCase = $this->createUseCase(sendMail: $sendMail);
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'manufacturer' => 'Dell',
            'model' => 'R740',
            'serial_number' => 'ABC',
            'location' => 'DC-1',
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
        $createJira->method('execute')->willThrowException(new \RuntimeException('Jira error'));

        $useCase = $this->createUseCase(createJira: $createJira);
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'manufacturer' => 'Dell',
            'model' => 'R740',
            'serial_number' => 'ABC',
            'location' => 'DC-1',
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
