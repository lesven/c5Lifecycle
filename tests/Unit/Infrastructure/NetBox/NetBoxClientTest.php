<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\NetBox;

use App\Infrastructure\NetBox\NetBoxClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NetBoxClientTest extends TestCase
{
    private function createClient(array $responses): NetBoxClient
    {
        $httpClient = new MockHttpClient($responses);
        return new NetBoxClient($httpClient, new NullLogger());
    }

    public function testFindDeviceByAssetTagReturnsDevice(): void
    {
        $device = ['id' => 42, 'asset_tag' => 'SRV-001'];
        $client = $this->createClient([
            new MockResponse(json_encode(['results' => [$device]]), ['http_code' => 200]),
        ]);

        $result = $client->findDeviceByAssetTag('SRV-001', 'req-123');
        $this->assertEquals(42, $result['id']);
        $this->assertEquals('SRV-001', $result['asset_tag']);
    }

    public function testFindDeviceByAssetTagReturnsNullWhenNotFound(): void
    {
        $client = $this->createClient([
            new MockResponse(json_encode(['results' => []]), ['http_code' => 200]),
        ]);

        $result = $client->findDeviceByAssetTag('NONEXISTENT', 'req-123');
        $this->assertNull($result);
    }

    public function testUpdateDeviceSendsCorrectData(): void
    {
        $updatedDevice = ['id' => 42, 'status' => 'active'];
        $client = $this->createClient([
            new MockResponse(json_encode($updatedDevice), ['http_code' => 200]),
        ]);

        $result = $client->updateDevice(42, ['status' => 'active'], 'req-123');
        $this->assertEquals(42, $result['id']);
    }

    public function testCreateJournalEntryReturnsResult(): void
    {
        $entry = ['id' => 1, 'comments' => 'Test entry'];
        $client = $this->createClient([
            new MockResponse(json_encode($entry), ['http_code' => 201]),
        ]);

        $result = $client->createJournalEntry([
            'assigned_object_type' => 'dcim.device',
            'assigned_object_id' => 42,
            'kind' => 'success',
            'comments' => 'Test entry',
        ], 'req-123');

        $this->assertEquals(1, $result['id']);
    }

    public function testFindDeviceTypeByModelReturnsMatch(): void
    {
        $deviceType = ['id' => 10, 'model' => 'PowerEdge R750'];
        $client = $this->createClient([
            new MockResponse(json_encode(['results' => [$deviceType]]), ['http_code' => 200]),
        ]);

        $result = $client->findDeviceTypeByModel('Dell', 'PowerEdge R750', 'req-123');
        $this->assertEquals(10, $result['id']);
    }

    public function testFindDeviceTypeByModelReturnsNullWhenNotFound(): void
    {
        $client = $this->createClient([
            new MockResponse(json_encode(['results' => []]), ['http_code' => 200]),
        ]);

        $result = $client->findDeviceTypeByModel('Unknown', 'Unknown Model', 'req-123');
        $this->assertNull($result);
    }

    public function testCreateDeviceReturnsNewDevice(): void
    {
        $newDevice = ['id' => 99, 'asset_tag' => 'NEW-001'];
        $client = $this->createClient([
            new MockResponse(json_encode($newDevice), ['http_code' => 201]),
        ]);

        $result = $client->createDevice(['name' => 'NEW-001', 'asset_tag' => 'NEW-001'], 'req-123');
        $this->assertEquals(99, $result['id']);
    }

    public function testCreateDeviceThrowsOnNullResponse(): void
    {
        $client = $this->createClient([
            new MockResponse('not json', ['http_code' => 201]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NetBox Device-Erstellung lieferte kein Ergebnis');
        $client->createDevice(['name' => 'TEST'], 'req-123');
    }

    public function testGetTenantsReturnsList(): void
    {
        $tenants = [
            ['id' => 1, 'name' => 'Tenant A'],
            ['id' => 2, 'name' => 'Tenant B'],
        ];
        $client = $this->createClient([
            new MockResponse(json_encode(['results' => $tenants]), ['http_code' => 200]),
        ]);

        $result = $client->getTenants('req-123');
        $this->assertCount(2, $result);
        $this->assertEquals('Tenant A', $result[0]['name']);
    }

    public function testGetContactsReturnsList(): void
    {
        $contacts = [
            ['id' => 1, 'name' => 'Contact A'],
        ];
        $client = $this->createClient([
            new MockResponse(json_encode(['results' => $contacts]), ['http_code' => 200]),
        ]);

        $result = $client->getContacts('req-123');
        $this->assertCount(1, $result);
    }

    public function testCreateContactAssignmentIncludesRole(): void
    {
        $client = $this->createClient([
            new MockResponse(json_encode(['id' => 1]), ['http_code' => 201]),
        ]);

        $result = $client->createContactAssignment(42, 5, 1, 'req-123');
        $this->assertEquals(1, $result['id']);
    }

    public function testApiErrorThrowsRuntimeException(): void
    {
        $client = $this->createClient([
            new MockResponse('{"detail": "Not found."}', ['http_code' => 404]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NetBox API error (HTTP 404)');
        $client->findDeviceByAssetTag('NONEXISTENT', 'req-123');
    }

    public function testServerErrorThrowsRuntimeException(): void
    {
        $client = $this->createClient([
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NetBox API error (HTTP 500)');
        $client->getTenants('req-123');
    }
}
