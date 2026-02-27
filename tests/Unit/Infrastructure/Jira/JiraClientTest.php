<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Jira;

use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\Jira\JiraClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class JiraClientTest extends TestCase
{
    private function createConfig(): EvidenceConfig
    {
        $configPath = sys_get_temp_dir() . '/c5_test_config_' . uniqid() . '.yaml';
        file_put_contents($configPath, "
smtp:
  host: localhost
  port: 1025
  from_address: test@example.com
  from_name: Test
evidence:
  rz_assets:
    to: test@example.com
    cc: []
jira:
  enabled: true
  base_url: https://jira.test
  project_key: ITOPS
  issue_type: Task
  api_token: test-token
");
        $config = EvidenceConfig::fromYamlFile($configPath);
        unlink($configPath);
        return $config;
    }

    private function createClient(array $responses): JiraClient
    {
        $httpClient = new MockHttpClient($responses);
        return new JiraClient($httpClient, $this->createConfig(), new NullLogger());
    }

    public function testCreateTicketReturnsKey(): void
    {
        $client = $this->createClient([
            new MockResponse(json_encode(['key' => 'ITOPS-42']), ['http_code' => 201]),
        ]);

        $event = ['label' => 'Inbetriebnahme RZ-Asset'];
        $data = ['asset_id' => 'SRV-001', 'device_type' => 'Server'];

        $ticket = $client->createTicket($event, $data, 'req-123');
        $this->assertEquals('ITOPS-42', $ticket);
    }

    public function testCreateTicketUsesUnknownForMissingAssetId(): void
    {
        $client = $this->createClient([
            new MockResponse(json_encode(['key' => 'ITOPS-99']), ['http_code' => 201]),
        ]);

        $event = ['label' => 'Test Event'];
        $ticket = $client->createTicket($event, [], 'req-456');
        $this->assertEquals('ITOPS-99', $ticket);
    }

    public function testCreateTicketHandlesBooleanValues(): void
    {
        $client = $this->createClient([
            new MockResponse(json_encode(['key' => 'ITOPS-50']), ['http_code' => 201]),
        ]);

        $event = ['label' => 'Test'];
        $data = ['monitoring_active' => true, 'patch_process' => false];

        $ticket = $client->createTicket($event, $data, 'req-789');
        $this->assertEquals('ITOPS-50', $ticket);
    }

    public function testCreateTicketThrowsOnApiError(): void
    {
        $client = $this->createClient([
            new MockResponse('{"errors":["permission denied"]}', ['http_code' => 403]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Jira API error (HTTP 403)');
        $client->createTicket(['label' => 'Test'], ['asset_id' => 'SRV-001'], 'req-123');
    }

    public function testCreateTicketThrowsOnMissingKey(): void
    {
        $client = $this->createClient([
            new MockResponse(json_encode(['id' => '12345']), ['http_code' => 201]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Jira response missing ticket key');
        $client->createTicket(['label' => 'Test'], ['asset_id' => 'SRV-001'], 'req-123');
    }
}
