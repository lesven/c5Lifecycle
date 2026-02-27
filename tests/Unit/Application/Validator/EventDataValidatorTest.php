<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Validator;

use App\Application\Validator\EventDataValidator;
use App\Domain\Service\EventRegistry;
use PHPUnit\Framework\TestCase;

class EventDataValidatorTest extends TestCase
{
    private EventDataValidator $validator;
    private EventRegistry $registry;

    protected function setUp(): void
    {
        $this->validator = new EventDataValidator();
        $this->registry = new EventRegistry();
    }

    public function testValidateReturnsEmptyForValidRzProvision(): void
    {
        $event = $this->registry->get('rz_provision');
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'manufacturer' => 'Dell',
            'model' => 'PowerEdge R740',
            'serial_number' => 'ABC123',
            'location' => 'DC-1 Rack A3',
            'commission_date' => '2024-01-15',
            'asset_owner' => 'Team Infrastructure',
            'service' => 'Kubernetes',
            'criticality' => 'Hoch',
            'change_ref' => 'CHG-001',
            'monitoring_active' => true,
            'patch_process' => true,
            'access_controlled' => true,
        ];
        $errors = $this->validator->validate('rz_provision', $event, $data);
        $this->assertEmpty($errors);
    }

    public function testValidateDetectsMissingRequiredFields(): void
    {
        $event = $this->registry->get('rz_provision');
        $data = ['asset_id' => 'SRV-001'];

        $errors = $this->validator->validate('rz_provision', $event, $data);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('device_type', $errors);
        $this->assertArrayHasKey('manufacturer', $errors);
        $this->assertArrayHasKey('model', $errors);
        $this->assertEquals('Pflichtfeld', $errors['device_type']);
    }

    public function testValidateDetectsEmptyStringAsInvalid(): void
    {
        $event = $this->registry->get('rz_provision');
        $data = [
            'asset_id' => '',
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
        $errors = $this->validator->validate('rz_provision', $event, $data);
        $this->assertArrayHasKey('asset_id', $errors);
    }

    public function testValidateDetectsFalseBooleanAsInvalid(): void
    {
        $event = $this->registry->get('rz_provision');
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
            'monitoring_active' => false,
            'patch_process' => true,
            'access_controlled' => true,
        ];
        $errors = $this->validator->validate('rz_provision', $event, $data);
        $this->assertArrayHasKey('monitoring_active', $errors);
    }

    public function testValidateRzRetireRequiresDataHandlingRefWhenDataHandlingIsNotNichtRelevant(): void
    {
        $event = $this->registry->get('rz_retire');
        $data = [
            'asset_id' => 'SRV-001',
            'retire_date' => '2024-06-01',
            'reason' => 'End of Life',
            'owner_approval' => true,
            'followup' => 'Entsorgung',
            'data_handling' => 'Gelöscht',
        ];
        $errors = $this->validator->validate('rz_retire', $event, $data);
        $this->assertArrayHasKey('data_handling_ref', $errors);
        $this->assertStringContainsString('Data Handling', $errors['data_handling_ref']);
    }

    public function testValidateRzRetireDoesNotRequireDataHandlingRefWhenNichtRelevant(): void
    {
        $event = $this->registry->get('rz_retire');
        $data = [
            'asset_id' => 'SRV-001',
            'retire_date' => '2024-06-01',
            'reason' => 'End of Life',
            'owner_approval' => true,
            'followup' => 'Entsorgung',
            'data_handling' => 'Nicht relevant',
        ];
        $errors = $this->validator->validate('rz_retire', $event, $data);
        $this->assertArrayNotHasKey('data_handling_ref', $errors);
    }

    public function testValidateRzRetireDoesNotRequireDataHandlingRefWhenEmpty(): void
    {
        $event = $this->registry->get('rz_retire');
        $data = [
            'asset_id' => 'SRV-001',
            'retire_date' => '2024-06-01',
            'reason' => 'End of Life',
            'owner_approval' => true,
            'followup' => 'Entsorgung',
            'data_handling' => '',
        ];
        $errors = $this->validator->validate('rz_retire', $event, $data);
        $this->assertArrayNotHasKey('data_handling_ref', $errors);
    }

    public function testValidateRzRetireWithDataHandlingRefPresent(): void
    {
        $event = $this->registry->get('rz_retire');
        $data = [
            'asset_id' => 'SRV-001',
            'retire_date' => '2024-06-01',
            'reason' => 'End of Life',
            'owner_approval' => true,
            'followup' => 'Entsorgung',
            'data_handling' => 'Gelöscht',
            'data_handling_ref' => 'WIPE-2024-001',
        ];
        $errors = $this->validator->validate('rz_retire', $event, $data);
        $this->assertArrayNotHasKey('data_handling_ref', $errors);
    }

    public function testValidateAccessCleanupRequiresTicketRefWhenDeviceNotWiped(): void
    {
        $event = $this->registry->get('admin_access_cleanup');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'cleanup_date' => '2024-06-01',
            'account_removed' => true,
            'keys_revoked' => true,
            'device_wiped' => false,
        ];
        $errors = $this->validator->validate('admin_access_cleanup', $event, $data);
        $this->assertArrayHasKey('ticket_ref', $errors);
        $this->assertStringContainsString('Wipe nicht abgeschlossen', $errors['ticket_ref']);
    }

    public function testValidateAccessCleanupDoesNotRequireTicketRefWhenDeviceWiped(): void
    {
        $event = $this->registry->get('admin_access_cleanup');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'cleanup_date' => '2024-06-01',
            'account_removed' => true,
            'keys_revoked' => true,
            'device_wiped' => true,
        ];
        $errors = $this->validator->validate('admin_access_cleanup', $event, $data);
        $this->assertArrayNotHasKey('ticket_ref', $errors);
    }

    public function testValidateAccessCleanupWithTicketRefProvided(): void
    {
        $event = $this->registry->get('admin_access_cleanup');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'cleanup_date' => '2024-06-01',
            'account_removed' => true,
            'keys_revoked' => true,
            'device_wiped' => false,
            'ticket_ref' => 'ITOPS-456',
        ];
        $errors = $this->validator->validate('admin_access_cleanup', $event, $data);
        $this->assertArrayNotHasKey('ticket_ref', $errors);
    }

    public function testValidateAccessCleanupRequiresTicketRefWhenDeviceWipedMissing(): void
    {
        $event = $this->registry->get('admin_access_cleanup');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'cleanup_date' => '2024-06-01',
            'account_removed' => true,
            'keys_revoked' => true,
        ];
        $errors = $this->validator->validate('admin_access_cleanup', $event, $data);
        $this->assertArrayHasKey('ticket_ref', $errors);
    }

    public function testValidateRzOwnerConfirmAllFieldsPresent(): void
    {
        $event = $this->registry->get('rz_owner_confirm');
        $data = [
            'asset_id' => 'SRV-001',
            'owner' => 'Team Infrastructure',
            'confirm_date' => '2024-06-01',
            'purpose_bound' => true,
            'change_process' => true,
            'admin_access_controlled' => true,
            'lifecycle_managed' => true,
        ];
        $errors = $this->validator->validate('rz_owner_confirm', $event, $data);
        $this->assertEmpty($errors);
    }

    public function testValidateAdminProvisionAllFieldsPresent(): void
    {
        $event = $this->registry->get('admin_provision');
        $data = [
            'asset_id' => 'WS-001',
            'device_type' => 'Laptop',
            'manufacturer' => 'Lenovo',
            'model' => 'ThinkPad X1',
            'serial_number' => 'LEN123',
            'commission_date' => '2024-01-15',
            'admin_user' => 'admin1',
            'security_owner' => 'IT Security',
            'purpose' => 'Privileged Admin Access',
            'disk_encryption' => true,
            'mfa_active' => true,
            'edr_active' => true,
            'patch_process' => true,
            'no_private_use' => true,
        ];
        $errors = $this->validator->validate('admin_provision', $event, $data);
        $this->assertEmpty($errors);
    }

    public function testValidateAdminUserCommitmentAllFieldsPresent(): void
    {
        $event = $this->registry->get('admin_user_commitment');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'commitment_date' => '2024-01-15',
            'admin_tasks_only' => true,
            'no_mail_office' => true,
            'no_credential_sharing' => true,
            'report_loss' => true,
            'return_on_change' => true,
        ];
        $errors = $this->validator->validate('admin_user_commitment', $event, $data);
        $this->assertEmpty($errors);
    }

    public function testValidateAdminReturnAllFieldsPresent(): void
    {
        $event = $this->registry->get('admin_return');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'return_date' => '2024-06-01',
            'return_reason' => 'Rollenwechsel',
            'condition' => 'Gut',
            'accessories_complete' => true,
        ];
        $errors = $this->validator->validate('admin_return', $event, $data);
        $this->assertEmpty($errors);
    }
}
