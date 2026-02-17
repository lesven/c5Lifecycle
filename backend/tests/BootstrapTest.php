<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Bootstrap;
use C5\Config;
use C5\Log\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Bootstrap uses exit() which makes direct testing difficult.
 * These tests verify the underlying logic by testing the components
 * Bootstrap depends on (Config loading and validation).
 */
class BootstrapTest extends TestCase
{
    private string $tmpLogDir;

    protected function setUp(): void
    {
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

    public function testConfigLoadSucceedsWithValidFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
        file_put_contents($tmpFile, \Symfony\Component\Yaml\Yaml::dump([
            'smtp' => ['host' => 'localhost', 'port' => 587],
            'evidence' => [
                'rz_assets' => ['to' => 'test@example.com', 'cc' => []],
            ],
        ], 4));

        $config = Config::load($tmpFile);

        // Bootstrap validates these two sections exist
        $this->assertTrue($config->has('smtp'));
        $this->assertTrue($config->has('evidence'));

        unlink($tmpFile);
    }

    public function testConfigLoadFailsWithMissingFile(): void
    {
        $this->expectException(\Exception::class);
        Config::load('/nonexistent/config.yaml');
    }

    public function testConfigMissingSmtpSection(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
        file_put_contents($tmpFile, \Symfony\Component\Yaml\Yaml::dump([
            'evidence' => ['rz_assets' => ['to' => 'test@example.com']],
        ], 4));

        $config = Config::load($tmpFile);

        // Bootstrap checks for 'smtp' section
        $this->assertFalse($config->has('smtp'));
        $this->assertTrue($config->has('evidence'));

        unlink($tmpFile);
    }

    public function testConfigMissingEvidenceSection(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
        file_put_contents($tmpFile, \Symfony\Component\Yaml\Yaml::dump([
            'smtp' => ['host' => 'localhost'],
        ], 4));

        $config = Config::load($tmpFile);

        // Bootstrap checks for 'evidence' section
        $this->assertTrue($config->has('smtp'));
        $this->assertFalse($config->has('evidence'));

        unlink($tmpFile);
    }

    public function testConfigWithAllRequiredSections(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
        file_put_contents($tmpFile, \Symfony\Component\Yaml\Yaml::dump([
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user',
                'password' => 'pass',
                'from_address' => 'c5@example.com',
                'from_name' => 'C5 Tool',
            ],
            'evidence' => [
                'rz_assets' => ['to' => 'rz@example.com', 'cc' => []],
                'admin_devices' => ['to' => 'admin@example.com', 'cc' => ['sec@example.com']],
            ],
            'jira' => ['enabled' => false],
        ], 4));

        $config = Config::load($tmpFile);

        // Verify all required sections exist (as Bootstrap would check)
        $required = ['smtp', 'evidence'];
        foreach ($required as $section) {
            $this->assertTrue($config->has($section), "Section '{$section}' should exist");
        }

        unlink($tmpFile);
    }
}
