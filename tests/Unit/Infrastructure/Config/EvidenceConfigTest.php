<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Config;

use App\Domain\ValueObject\JiraRule;
use App\Domain\ValueObject\NetBoxSyncRule;
use App\Infrastructure\Config\EvidenceConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EvidenceConfigTest extends TestCase
{
    private function writeTempYaml(string $yaml): string
    {
        $path = sys_get_temp_dir() . '/c5_config_test_' . uniqid() . '.yaml';
        file_put_contents($path, $yaml);

        return $path;
    }

    public function testFromYamlFileLoadsValidConfig(): void
    {
        $path = $this->writeTempYaml('
smtp:
  from_address: test@example.com
  from_name: Test
evidence:
  rz_assets:
    to: rz@example.com
  admin_devices:
    to: admin@example.com
');
        $config = EvidenceConfig::fromYamlFile($path);
        unlink($path);

        $this->assertSame('test@example.com', $config->getSmtpFromAddress());
        $this->assertSame('Test', $config->getSmtpFromName());
    }

    public function testFromYamlFileThrowsOnMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config file not found');
        EvidenceConfig::fromYamlFile('/nonexistent/path.yaml');
    }

    public function testFromYamlFileThrowsOnMissingSmtpFromAddress(): void
    {
        $path = $this->writeTempYaml('
smtp:
  from_name: Test
evidence:
  rz_assets:
    to: rz@example.com
  admin_devices:
    to: admin@example.com
');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('smtp.from_address');
        try {
            EvidenceConfig::fromYamlFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testFromYamlFileThrowsOnMissingEvidenceRecipients(): void
    {
        $path = $this->writeTempYaml('
smtp:
  from_address: test@example.com
  from_name: Test
evidence:
  rz_assets:
    to: rz@example.com
');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('evidence.admin_devices.to');
        try {
            EvidenceConfig::fromYamlFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testFromYamlFileReportsAllMissingKeys(): void
    {
        $path = $this->writeTempYaml('
netbox:
  enabled: false
');
        try {
            EvidenceConfig::fromYamlFile($path);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('smtp.from_address', $e->getMessage());
            $this->assertStringContainsString('evidence.rz_assets.to', $e->getMessage());
            $this->assertStringContainsString('evidence.admin_devices.to', $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $path = $this->writeTempYaml('
smtp:
  from_address: test@example.com
  from_name: Test
evidence:
  rz_assets:
    to: rz@example.com
  admin_devices:
    to: admin@example.com
');
        $config = EvidenceConfig::fromYamlFile($path);
        unlink($path);

        $this->assertSame('default', $config->get('nonexistent.key', 'default'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $path = $this->writeTempYaml('
smtp:
  from_address: test@example.com
  from_name: Test
evidence:
  rz_assets:
    to: rz@example.com
  admin_devices:
    to: admin@example.com
');
        $config = EvidenceConfig::fromYamlFile($path);
        unlink($path);

        $this->assertFalse($config->has('nonexistent.key'));
        $this->assertTrue($config->has('smtp.from_address'));
    }

    public function testJiraRuleReturnsNoneWhenJiraDisabled(): void
    {
        $path = $this->writeTempYaml('
smtp:
  from_address: test@example.com
  from_name: Test
evidence:
  rz_assets:
    to: rz@example.com
  admin_devices:
    to: admin@example.com
jira:
  enabled: false
jira_rules:
  rz_retire: optional
');
        $config = EvidenceConfig::fromYamlFile($path);
        unlink($path);

        $this->assertSame(JiraRule::None, $config->getJiraRule('rz_retire'));
    }

    public function testNetBoxSyncRuleReturnsNoneWhenDisabled(): void
    {
        $path = $this->writeTempYaml('
smtp:
  from_address: test@example.com
  from_name: Test
evidence:
  rz_assets:
    to: rz@example.com
  admin_devices:
    to: admin@example.com
netbox:
  enabled: false
  sync_rules:
    rz_provision: update_status
');
        $config = EvidenceConfig::fromYamlFile($path);
        unlink($path);

        $this->assertSame(NetBoxSyncRule::None, $config->getNetBoxSyncRule('rz_provision'));
    }
}
