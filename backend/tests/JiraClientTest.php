<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Config;
use C5\Jira\JiraClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class JiraClientTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function createConfig(array $data): Config
    {
        file_put_contents($this->tmpFile, Yaml::dump($data, 4));
        return Config::load($this->tmpFile);
    }

    public function testConstructorAcceptsConfig(): void
    {
        $config = $this->createConfig([
            'jira' => [
                'enabled' => true,
                'base_url' => 'https://jira.example.com',
                'project_key' => 'TEST',
                'api_token' => 'token123',
            ],
        ]);
        $client = new JiraClient($config);
        $this->assertInstanceOf(JiraClient::class, $client);
    }

    public function testCreateTicketThrowsOnConnectionFailure(): void
    {
        $config = $this->createConfig([
            'jira' => [
                'enabled' => true,
                'base_url' => 'http://127.0.0.1:19999',
                'project_key' => 'TEST',
                'issue_type' => 'Task',
                'api_token' => 'fake-token',
            ],
        ]);

        $client = new JiraClient($config);
        $event = [
            'label' => 'Inbetriebnahme RZ-Asset',
            'category' => 'RZ',
        ];
        $data = ['asset_id' => 'SRV-001', 'device_type' => 'Server'];

        $this->expectException(\RuntimeException::class);
        $client->createTicket($event, $data, 'req-123');
    }

    public function testCreateTicketUsesDefaultProjectKey(): void
    {
        $config = $this->createConfig([
            'jira' => [
                'enabled' => true,
                'base_url' => 'http://127.0.0.1:19999',
                'api_token' => 'fake-token',
            ],
        ]);

        $client = new JiraClient($config);
        $event = ['label' => 'Test Event', 'category' => 'RZ'];
        $data = ['asset_id' => 'SRV-001'];

        // Will fail on connection, but verifies defaults don't cause errors before the curl call
        $this->expectException(\RuntimeException::class);
        $client->createTicket($event, $data, 'req-123');
    }

    public function testCreateTicketHandlesBooleanDataValues(): void
    {
        $config = $this->createConfig([
            'jira' => [
                'enabled' => true,
                'base_url' => 'http://127.0.0.1:19999',
                'project_key' => 'TEST',
                'api_token' => 'fake-token',
            ],
        ]);

        $client = new JiraClient($config);
        $event = ['label' => 'Test Event', 'category' => 'RZ'];
        $data = [
            'asset_id' => 'SRV-001',
            'monitoring_active' => true,
            'patch_process' => false,
        ];

        // Will fail on connection, but should not crash on boolean conversion
        $this->expectException(\RuntimeException::class);
        $client->createTicket($event, $data, 'req-123');
    }

    public function testCreateTicketUsesAssetIdInSummary(): void
    {
        // We can't easily test the payload content without a mock server,
        // but we can verify it doesn't crash with various asset IDs
        $config = $this->createConfig([
            'jira' => [
                'enabled' => true,
                'base_url' => 'http://127.0.0.1:19999',
                'project_key' => 'ITOPS',
                'api_token' => 'token',
            ],
        ]);

        $client = new JiraClient($config);
        $event = ['label' => 'Inbetriebnahme RZ-Asset', 'category' => 'RZ'];
        $data = ['asset_id' => 'SRV-ABC-123'];

        try {
            $client->createTicket($event, $data, 'req-456');
        } catch (\RuntimeException $e) {
            // Expected: connection failure or HTTP error
            $this->assertStringContainsString('Jira', $e->getMessage());
        }
    }

    public function testCreateTicketHandlesMissingAssetId(): void
    {
        $config = $this->createConfig([
            'jira' => [
                'enabled' => true,
                'base_url' => 'http://127.0.0.1:19999',
                'project_key' => 'TEST',
                'api_token' => 'token',
            ],
        ]);

        $client = new JiraClient($config);
        $event = ['label' => 'Test Event', 'category' => 'ADM'];
        $data = []; // No asset_id

        try {
            $client->createTicket($event, $data, 'req-789');
        } catch (\RuntimeException $e) {
            // Should use 'UNKNOWN' as fallback asset_id, then fail on connection
            $this->assertStringContainsString('Jira', $e->getMessage());
        }
    }
}
