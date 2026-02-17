<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Config;
use C5\Handler\AssetLookupHandler;
use C5\Log\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class AssetLookupHandlerTest extends TestCase
{
    private string $tmpConfigFile;
    private string $tmpLogDir;

    protected function setUp(): void
    {
        $this->tmpConfigFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
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

    private function createConfig(bool $netboxEnabled = false): Config
    {
        $data = [
            'smtp' => ['host' => 'localhost'],
            'evidence' => [],
            'netbox' => [
                'enabled' => $netboxEnabled,
                'base_url' => 'https://netbox.invalid.test',
                'api_token' => 'test-token',
                'timeout' => 2,
                'verify_ssl' => false,
            ],
        ];
        file_put_contents($this->tmpConfigFile, Yaml::dump($data, 4));
        return Config::load($this->tmpConfigFile);
    }

    public function testHandleReturnsNetboxDisabledWhenNotEnabled(): void
    {
        $_GET['asset_id'] = 'SRV-001';
        $config = $this->createConfig(false);
        $handler = new AssetLookupHandler($config);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['found']);
        $this->assertEquals('netbox_disabled', $data['reason']);

        unset($_GET['asset_id']);
    }

    public function testHandleReturnsMissingAssetIdWhenEmpty(): void
    {
        $_GET['asset_id'] = '';
        $config = $this->createConfig(true);
        $handler = new AssetLookupHandler($config);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['found']);
        $this->assertEquals('missing_asset_id', $data['reason']);

        unset($_GET['asset_id']);
    }

    public function testHandleReturnsMissingAssetIdWhenNotProvided(): void
    {
        unset($_GET['asset_id']);
        $config = $this->createConfig(true);
        $handler = new AssetLookupHandler($config);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['found']);
        $this->assertEquals('missing_asset_id', $data['reason']);
    }

    public function testHandleReturnsNetboxErrorOnConnectionFailure(): void
    {
        $_GET['asset_id'] = 'SRV-001';
        $config = $this->createConfig(true);
        $handler = new AssetLookupHandler($config);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['found']);
        $this->assertEquals('netbox_error', $data['reason']);

        unset($_GET['asset_id']);
    }
}
