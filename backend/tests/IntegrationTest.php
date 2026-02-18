<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Config;
use C5\EventRegistry;
use C5\Mail\MailBuilder;
use C5\NetBox\StatusMapper;
use C5\NetBox\CustomFieldMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class IntegrationTest extends TestCase
{
    private Config $config;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
        file_put_contents($this->tmpFile, Yaml::dump([
            'smtp' => ['host' => 'localhost'],
            'evidence' => [
                'rz_assets' => ['to' => 'rz@example.com', 'cc' => []],
                'admin_devices' => ['to' => 'admin@example.com', 'cc' => []],
            ],
            'netbox' => [
                'enabled' => true,
                'sync_rules' => [
                    'rz_provision' => 'required',
                    'rz_retire' => 'optional',
                ]
            ],
            'jira' => [
                'enabled' => true,
            ],
            'jira_rules' => [
                'rz_provision' => 'optional',
                'admin_return' => 'required',
            ],
        ], 4));
        $this->config = Config::load($this->tmpFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testFullWorkflowForRzProvision(): void
    {
        // Test complete workflow for RZ provision event
        $eventType = 'rz_provision';
        $event = EventRegistry::get($eventType);
        
        $this->assertNotNull($event);
        $this->assertEquals('rz_assets', $event['track']);
        $this->assertEquals('RZ', $event['category']);
        
        // Test status mapping
        $status = StatusMapper::getTargetStatus($eventType);
        $this->assertEquals('active', $status);
        
        // Test custom field mapping
        $this->assertTrue(CustomFieldMapper::hasMapping($eventType));
        $mappings = CustomFieldMapper::map($eventType, [
            'asset_id' => 'SRV-001',
            'asset_owner' => 'IT Team',
            'service' => 'Web Server',
        ]);
        $this->assertNotEmpty($mappings);
        
        // Test mail building
        $data = [
            'asset_id' => 'SRV-001',
            'device_type' => 'Server',
            'manufacturer' => 'Dell',
            'model' => 'PowerEdge R740',
            'asset_owner' => 'IT-Team München',
        ];
        $mailBody = MailBuilder::build($event, $data, 'req-integration-test-001');
        
        $this->assertStringContainsString('SRV-001', $mailBody);
        $this->assertStringContainsString('München', $mailBody);
        $this->assertStringContainsString('C5 EVIDENCE', $mailBody); // Header enthält C5 EVIDENCE, nicht [C5 Evidence]
        
        // Test config integration
        $recipients = $this->config->getEvidenceRecipients($event['track']);
        $this->assertEquals('rz@example.com', $recipients['to']);
        
        $jiraRule = $this->config->getJiraRule($eventType);
        $this->assertEquals('optional', $jiraRule);
        
        $netboxRule = $this->config->getNetBoxSyncRule($eventType);
        $this->assertEquals('required', $netboxRule);
    }

    public function testFullWorkflowForAdminReturn(): void
    {
        // Test complete workflow for admin return event
        $eventType = 'admin_return';
        $event = EventRegistry::get($eventType);
        
        $this->assertNotNull($event);
        $this->assertEquals('admin_devices', $event['track']);
        $this->assertEquals('ADM', $event['category']);
        
        // Test status mapping
        $status = StatusMapper::getTargetStatus($eventType);
        $this->assertEquals('inventory', $status);
        
        // Test mail building with German content
        $data = [
            'asset_id' => 'WS-001',
            'admin_user' => 'mueller@company.de',
            'return_date' => '2024-02-18',
            'condition' => 'Gerät in ordnungsgemäßem Zustand zurückgegeben',
        ];
        $mailBody = MailBuilder::build($event, $data, 'req-admin-return-001');
        
        $this->assertStringContainsString('WS-001', $mailBody);
        $this->assertStringContainsString('ordnungsgemäßem', $mailBody);
        
        // Test config integration
        $recipients = $this->config->getEvidenceRecipients($event['track']);
        $this->assertEquals('admin@example.com', $recipients['to']);
        
        $jiraRule = $this->config->getJiraRule($eventType);
        // admin_return is in the jira rules config as 'required'  
        $this->assertEquals('required', $jiraRule);
    }

    public function testEventRegistryCompleteness(): void
    {
        // Ensure all expected events are registered
        $expectedEvents = [
            'rz_provision',
            'rz_retire', 
            'rz_owner_confirm',
            'admin_provision',
            'admin_user_commitment',
            'admin_return',
            'admin_access_cleanup',
        ];
        
        foreach ($expectedEvents as $eventType) {
            $this->assertTrue(EventRegistry::exists($eventType), 
                "Event '{$eventType}' should exist in registry");
            
            $event = EventRegistry::get($eventType);
            $this->assertArrayHasKey('track', $event);
            $this->assertArrayHasKey('label', $event);
            $this->assertArrayHasKey('category', $event);
            $this->assertArrayHasKey('required_fields', $event);
            
            // Each event must have asset_id as required field
            $this->assertContains('asset_id', $event['required_fields'], 
                "Event '{$eventType}' must require asset_id field");
        }
    }

    public function testGermanLocalizationConsistency(): void
    {
        // Test that all components use consistent German terminology
        $rzEvents = ['rz_provision', 'rz_retire', 'rz_owner_confirm'];
        $adminEvents = ['admin_provision', 'admin_user_commitment', 'admin_return', 'admin_access_cleanup'];
        
        foreach ($rzEvents as $eventType) {
            $event = EventRegistry::get($eventType);
            $this->assertEquals('RZ', $event['category']);
            $this->assertEquals('rz_assets', $event['track']);
        }
        
        foreach ($adminEvents as $eventType) {
            $event = EventRegistry::get($eventType);
            $this->assertEquals('ADM', $event['category']);
            $this->assertEquals('admin_devices', $event['track']);
        }
    }

    public function testSubjectLineGeneration(): void
    {
        // Test that subject lines follow the required format
        $testCases = [
            ['rz_provision', 'SRV-001', 'RZ', 'Inbetriebnahme'],
            ['rz_retire', 'SRV-002', 'RZ', 'Außerbetriebnahme'],
            ['admin_return', 'WS-001', 'ADM', 'Rückgabe'],
        ];
        
        foreach ($testCases as [$eventType, $assetId, $expectedCategory, $expectedSubjectType]) {
            $subject = EventRegistry::buildSubject($eventType, $assetId);
            
            $this->assertStringContainsString('[C5 Evidence]', $subject);
            $this->assertStringContainsString($expectedCategory, $subject);
            $this->assertStringContainsString($expectedSubjectType, $subject);
            $this->assertStringContainsString($assetId, $subject);
        }
    }
}