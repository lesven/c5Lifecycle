<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Config;
use C5\NetBox\NetBoxClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class NetBoxClientTest extends TestCase
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

    private function createConfig(array $netbox = []): Config
    {
        $data = [
            'smtp' => ['host' => 'localhost'],
            'evidence' => [],
            'netbox' => array_merge([
                'enabled' => true,
                'base_url' => 'https://netbox.invalid.test',
                'api_token' => 'test-token',
                'timeout' => 2,
                'verify_ssl' => false,
            ], $netbox),
        ];
        file_put_contents($this->tmpFile, Yaml::dump($data, 4));
        return Config::load($this->tmpFile);
    }

    public function testConstructorAcceptsConfig(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->assertInstanceOf(NetBoxClient::class, $client);
    }

    public function testFindDeviceByAssetTagThrowsOnConnectionFailure(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        $client->findDeviceByAssetTag('SRV-001', 'test-req');
    }

    public function testUpdateDeviceThrowsOnConnectionFailure(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        $client->updateDevice(42, ['status' => 'active'], 'test-req');
    }

    public function testCreateJournalEntryThrowsOnConnectionFailure(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        $client->createJournalEntry([
            'assigned_object_type' => 'dcim.device',
            'assigned_object_id' => 42,
            'kind' => 'info',
            'comments' => 'Test entry',
        ], 'test-req');
    }

    public function testGetThrowsOnConnectionFailure(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        $client->get('/api/dcim/devices/', [], 'test-req');
    }

    public function testPatchThrowsOnConnectionFailure(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        $client->patch('/api/dcim/devices/1/', ['status' => 'active'], 'test-req');
    }

    public function testPostThrowsOnConnectionFailure(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        $client->post('/api/extras/journal-entries/', ['test' => 'data'], 'test-req');
    }

    public function testConstructorWithCustomTimeout(): void
    {
        $client = new NetBoxClient($this->createConfig(['timeout' => 5]));
        $this->assertInstanceOf(NetBoxClient::class, $client);
    }

    public function testConstructorWithSslVerificationEnabled(): void
    {
        $client = new NetBoxClient($this->createConfig(['verify_ssl' => true]));
        $this->assertInstanceOf(NetBoxClient::class, $client);
    }

    public function testFindDeviceByAssetTagWithGermanCharacters(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        // Test with German characters in asset tag
        $client->findDeviceByAssetTag('SRV-München-001', 'test-äöü');
    }

    public function testUpdateDeviceWithGermanStatusValues(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        // Test with German status mappings
        $client->updateDevice(42, [
            'status' => 'active',
            'comments' => 'Gerät erfolgreich in Betrieb genommen - München'
        ], 'test-req');
    }

    public function testCreateJournalEntryWithGermanContent(): void
    {
        $client = new NetBoxClient($this->createConfig());
        $this->expectException(\RuntimeException::class);
        
        $journalData = [
            'assigned_object_type' => 'dcim.device',
            'assigned_object_id' => 42,
            'kind' => 'info',
            'comments' => 'Inbetriebnahme durchgeführt - Standort: München, Verantwortlicher: Müller',
        ];
        
        $client->createJournalEntry($journalData, 'test-req-äöü');
    }

    public function testClientHandlesCustomApiPaths(): void
    {
        $client = new NetBoxClient($this->createConfig());
        
        // Test various API endpoints
        $this->expectException(\RuntimeException::class);
        $client->get('/api/dcim/device-types/', ['manufacturer' => 'Dell'], 'test-req');
    }
}
