<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ConfigTest extends TestCase
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

    public function testLoadValidYaml(): void
    {
        $config = $this->createConfig(['smtp' => ['host' => 'localhost']]);
        $this->assertInstanceOf(Config::class, $config);
    }

    public function testLoadNonexistentFileThrows(): void
    {
        $this->expectException(\Exception::class);
        Config::load('/nonexistent/path/config.yaml');
    }

    public function testLoadInvalidFormatThrows(): void
    {
        file_put_contents($this->tmpFile, '--- true');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid config file format');
        Config::load($this->tmpFile);
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $config = $this->createConfig(['smtp' => ['host' => 'localhost']]);
        $this->assertTrue($config->has('smtp'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $config = $this->createConfig(['smtp' => ['host' => 'localhost']]);
        $this->assertFalse($config->has('jira'));
    }

    public function testGetTopLevelKey(): void
    {
        $config = $this->createConfig(['smtp' => ['host' => 'mail.example.com']]);
        $this->assertEquals(['host' => 'mail.example.com'], $config->get('smtp'));
    }

    public function testGetDotNotation(): void
    {
        $config = $this->createConfig(['smtp' => ['host' => 'mail.example.com', 'port' => 587]]);
        $this->assertEquals('mail.example.com', $config->get('smtp.host'));
        $this->assertEquals(587, $config->get('smtp.port'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $config = $this->createConfig(['smtp' => ['host' => 'localhost']]);
        $this->assertNull($config->get('nonexistent'));
        $this->assertEquals('fallback', $config->get('nonexistent', 'fallback'));
    }

    public function testGetReturnsDefaultForMissingNestedKey(): void
    {
        $config = $this->createConfig(['smtp' => ['host' => 'localhost']]);
        $this->assertEquals('default', $config->get('smtp.nonexistent', 'default'));
        $this->assertEquals('default', $config->get('a.b.c', 'default'));
    }

    public function testGetDeepDotNotation(): void
    {
        $config = $this->createConfig([
            'evidence' => [
                'rz_assets' => ['to' => 'test@example.com', 'cc' => ['cc1@example.com']],
            ],
        ]);
        $this->assertEquals('test@example.com', $config->get('evidence.rz_assets.to'));
        $this->assertEquals(['cc1@example.com'], $config->get('evidence.rz_assets.cc'));
    }

    public function testGetEvidenceRecipientsRzAssets(): void
    {
        $config = $this->createConfig([
            'evidence' => [
                'rz_assets' => ['to' => 'rz@example.com', 'cc' => ['sec@example.com']],
            ],
        ]);
        $result = $config->getEvidenceRecipients('rz_assets');
        $this->assertEquals('rz@example.com', $result['to']);
        $this->assertEquals(['sec@example.com'], $result['cc']);
    }

    public function testGetEvidenceRecipientsAdminDevices(): void
    {
        $config = $this->createConfig([
            'evidence' => [
                'admin_devices' => ['to' => 'admin@example.com', 'cc' => []],
            ],
        ]);
        $result = $config->getEvidenceRecipients('admin_devices');
        $this->assertEquals('admin@example.com', $result['to']);
        $this->assertEquals([], $result['cc']);
    }

    public function testGetEvidenceRecipientsDefaultsForMissingTrack(): void
    {
        $config = $this->createConfig(['evidence' => []]);
        $result = $config->getEvidenceRecipients('nonexistent');
        $this->assertEquals('', $result['to']);
        $this->assertEquals([], $result['cc']);
    }

    public function testGetJiraRuleWhenJiraDisabled(): void
    {
        $config = $this->createConfig([
            'jira' => ['enabled' => false],
            'jira_rules' => ['rz_provision' => 'required'],
        ]);
        $this->assertEquals('none', $config->getJiraRule('rz_provision'));
    }

    public function testGetJiraRuleWhenJiraEnabled(): void
    {
        $config = $this->createConfig([
            'jira' => ['enabled' => true],
            'jira_rules' => [
                'rz_provision' => 'none',
                'rz_retire' => 'optional',
                'admin_return' => 'required',
            ],
        ]);
        $this->assertEquals('none', $config->getJiraRule('rz_provision'));
        $this->assertEquals('optional', $config->getJiraRule('rz_retire'));
        $this->assertEquals('required', $config->getJiraRule('admin_return'));
    }

    public function testGetJiraRuleDefaultsToNoneForUnknownEvent(): void
    {
        $config = $this->createConfig([
            'jira' => ['enabled' => true],
            'jira_rules' => [],
        ]);
        $this->assertEquals('none', $config->getJiraRule('unknown_event'));
    }

    public function testGetJiraRuleDefaultsToNoneWhenJiraSectionMissing(): void
    {
        $config = $this->createConfig(['smtp' => []]);
        $this->assertEquals('none', $config->getJiraRule('rz_provision'));
    }
}
