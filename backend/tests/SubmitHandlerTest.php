<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Config;
use C5\EventRegistry;
use C5\Handler\SubmitHandler;
use C5\Log\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class SubmitHandlerTest extends TestCase
{
    private Config $config;
    private string $tmpConfigFile;
    private string $tmpLogDir;

    protected function setUp(): void
    {
        $this->tmpConfigFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
        file_put_contents($this->tmpConfigFile, Yaml::dump([
            'smtp' => ['host' => 'localhost', 'port' => 587],
            'evidence' => [
                'rz_assets' => ['to' => 'rz@example.com', 'cc' => []],
                'admin_devices' => ['to' => 'admin@example.com', 'cc' => []],
            ],
            'jira' => ['enabled' => false],
        ], 4));
        $this->config = Config::load($this->tmpConfigFile);

        // Redirect logger to temp dir
        $this->tmpLogDir = sys_get_temp_dir() . '/c5test_logs_' . uniqid();
        mkdir($this->tmpLogDir, 0755, true);
        $ref = new \ReflectionClass(Logger::class);
        $prop = $ref->getProperty('logDir');
        $prop->setAccessible(true);
        $prop->setValue(null, $this->tmpLogDir);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpConfigFile)) {
            unlink($this->tmpConfigFile);
        }
        // Clean up logs
        $files = glob($this->tmpLogDir . '/*.log');
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpLogDir)) {
            rmdir($this->tmpLogDir);
        }

        $ref = new \ReflectionClass(Logger::class);
        $prop = $ref->getProperty('logDir');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * Access the private validate method via reflection
     */
    private function callValidate(SubmitHandler $handler, string $eventType, array $event, array $data): array
    {
        $method = new \ReflectionMethod(SubmitHandler::class, 'validate');
        $method->setAccessible(true);
        return $method->invoke($handler, $eventType, $event, $data);
    }

    // --- Validation Tests ---

    public function testValidateReturnsEmptyForValidRzProvision(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_provision');
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
        $errors = $this->callValidate($handler, 'rz_provision', $event, $data);
        $this->assertEmpty($errors);
    }

    public function testValidateDetectsMissingRequiredFields(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_provision');
        $data = ['asset_id' => 'SRV-001']; // missing most required fields

        $errors = $this->callValidate($handler, 'rz_provision', $event, $data);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('device_type', $errors);
        $this->assertArrayHasKey('manufacturer', $errors);
        $this->assertArrayHasKey('model', $errors);
        $this->assertEquals('Pflichtfeld', $errors['device_type']);
    }

    public function testValidateDetectsEmptyStringAsInvalid(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_provision');
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
        $errors = $this->callValidate($handler, 'rz_provision', $event, $data);
        $this->assertArrayHasKey('asset_id', $errors);
    }

    public function testValidateDetectsFalseBooleanAsInvalid(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_provision');
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
            'monitoring_active' => false, // false boolean = invalid for checkbox
            'patch_process' => true,
            'access_controlled' => true,
        ];
        $errors = $this->callValidate($handler, 'rz_provision', $event, $data);
        $this->assertArrayHasKey('monitoring_active', $errors);
    }

    // --- Conditional Validation: rz_retire ---

    public function testValidateRzRetireRequiresDataHandlingRefWhenDataHandlingIsNotNichtRelevant(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_retire');
        $data = [
            'asset_id' => 'SRV-001',
            'retire_date' => '2024-06-01',
            'reason' => 'End of Life',
            'owner_approval' => true,
            'followup' => 'Entsorgung',
            'data_handling' => 'Gelöscht', // != "Nicht relevant"
            // data_handling_ref is missing
        ];
        $errors = $this->callValidate($handler, 'rz_retire', $event, $data);
        $this->assertArrayHasKey('data_handling_ref', $errors);
        $this->assertStringContainsString('Data Handling', $errors['data_handling_ref']);
    }

    public function testValidateRzRetireDoesNotRequireDataHandlingRefWhenNichtRelevant(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_retire');
        $data = [
            'asset_id' => 'SRV-001',
            'retire_date' => '2024-06-01',
            'reason' => 'End of Life',
            'owner_approval' => true,
            'followup' => 'Entsorgung',
            'data_handling' => 'Nicht relevant',
        ];
        $errors = $this->callValidate($handler, 'rz_retire', $event, $data);
        $this->assertArrayNotHasKey('data_handling_ref', $errors);
    }

    public function testValidateRzRetireDoesNotRequireDataHandlingRefWhenEmpty(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_retire');
        $data = [
            'asset_id' => 'SRV-001',
            'retire_date' => '2024-06-01',
            'reason' => 'End of Life',
            'owner_approval' => true,
            'followup' => 'Entsorgung',
            'data_handling' => '',
        ];
        $errors = $this->callValidate($handler, 'rz_retire', $event, $data);
        // data_handling is required field, so it'll fail there, but data_handling_ref should not be required
        $this->assertArrayNotHasKey('data_handling_ref', $errors);
    }

    public function testValidateRzRetireWithDataHandlingRefPresent(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_retire');
        $data = [
            'asset_id' => 'SRV-001',
            'retire_date' => '2024-06-01',
            'reason' => 'End of Life',
            'owner_approval' => true,
            'followup' => 'Entsorgung',
            'data_handling' => 'Gelöscht',
            'data_handling_ref' => 'WIPE-2024-001',
        ];
        $errors = $this->callValidate($handler, 'rz_retire', $event, $data);
        $this->assertArrayNotHasKey('data_handling_ref', $errors);
    }

    // --- Conditional Validation: admin_access_cleanup ---

    public function testValidateAccessCleanupRequiresTicketRefWhenDeviceNotWiped(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('admin_access_cleanup');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'cleanup_date' => '2024-06-01',
            'account_removed' => true,
            'keys_revoked' => true,
            'device_wiped' => false,
            // ticket_ref missing
        ];
        $errors = $this->callValidate($handler, 'admin_access_cleanup', $event, $data);
        $this->assertArrayHasKey('ticket_ref', $errors);
        $this->assertStringContainsString('Wipe nicht abgeschlossen', $errors['ticket_ref']);
    }

    public function testValidateAccessCleanupDoesNotRequireTicketRefWhenDeviceWiped(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('admin_access_cleanup');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'cleanup_date' => '2024-06-01',
            'account_removed' => true,
            'keys_revoked' => true,
            'device_wiped' => true,
        ];
        $errors = $this->callValidate($handler, 'admin_access_cleanup', $event, $data);
        $this->assertArrayNotHasKey('ticket_ref', $errors);
    }

    public function testValidateAccessCleanupWithTicketRefProvided(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('admin_access_cleanup');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'cleanup_date' => '2024-06-01',
            'account_removed' => true,
            'keys_revoked' => true,
            'device_wiped' => false,
            'ticket_ref' => 'ITOPS-456',
        ];
        $errors = $this->callValidate($handler, 'admin_access_cleanup', $event, $data);
        $this->assertArrayNotHasKey('ticket_ref', $errors);
    }

    public function testValidateAccessCleanupRequiresTicketRefWhenDeviceWipedMissing(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('admin_access_cleanup');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'cleanup_date' => '2024-06-01',
            'account_removed' => true,
            'keys_revoked' => true,
            // device_wiped not set at all
        ];
        $errors = $this->callValidate($handler, 'admin_access_cleanup', $event, $data);
        $this->assertArrayHasKey('ticket_ref', $errors);
    }

    // --- Validation for other event types ---

    public function testValidateRzOwnerConfirmAllFieldsPresent(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('rz_owner_confirm');
        $data = [
            'asset_id' => 'SRV-001',
            'owner' => 'Team Infrastructure',
            'confirm_date' => '2024-06-01',
            'purpose_bound' => true,
            'change_process' => true,
            'admin_access_controlled' => true,
            'lifecycle_managed' => true,
        ];
        $errors = $this->callValidate($handler, 'rz_owner_confirm', $event, $data);
        $this->assertEmpty($errors);
    }

    public function testValidateAdminProvisionAllFieldsPresent(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('admin_provision');
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
        $errors = $this->callValidate($handler, 'admin_provision', $event, $data);
        $this->assertEmpty($errors);
    }

    public function testValidateAdminUserCommitmentAllFieldsPresent(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('admin_user_commitment');
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
        $errors = $this->callValidate($handler, 'admin_user_commitment', $event, $data);
        $this->assertEmpty($errors);
    }

    public function testValidateAdminReturnAllFieldsPresent(): void
    {
        $handler = new SubmitHandler($this->config);
        $event = EventRegistry::get('admin_return');
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'admin1',
            'return_date' => '2024-06-01',
            'return_reason' => 'Rollenwechsel',
            'condition' => 'Gut',
            'accessories_complete' => true,
        ];
        $errors = $this->callValidate($handler, 'admin_return', $event, $data);
        $this->assertEmpty($errors);
    }

    // --- Handle method tests ---

    public function testHandleRejectsUnknownEventType(): void
    {
        $handler = new SubmitHandler($this->config);
        ob_start();
        $handler->handle('nonexistent_event');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Unbekannter Event-Typ', $data['error']);
        $this->assertArrayHasKey('request_id', $data);
    }

    public function testHandleRejectsEmptyBody(): void
    {
        $handler = new SubmitHandler($this->config);
        ob_start();
        $handler->handle('rz_provision');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        // php://input is empty in CLI, so json_decode returns null => invalid JSON
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('request_id', $data);
    }

    public function testHandleGeneratesRequestId(): void
    {
        $handler = new SubmitHandler($this->config);
        ob_start();
        $handler->handle('nonexistent_event');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertArrayHasKey('request_id', $data);
        // UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $data['request_id']
        );
    }
}
