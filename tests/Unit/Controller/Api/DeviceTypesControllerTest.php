<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\DeviceTypesController;
use App\Domain\Repository\EvidenceConfigInterface;
use App\Domain\Repository\NetBoxClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

class DeviceTypesControllerTest extends TestCase
{
    private function createController(
        bool $netboxEnabled,
        ?NetBoxClientInterface $netBoxClient = null,
    ): DeviceTypesController {
        $config = $this->createMock(EvidenceConfigInterface::class);
        $config->method('isNetBoxEnabled')->willReturn($netboxEnabled);

        return new DeviceTypesController(
            $netBoxClient ?? $this->createMock(NetBoxClientInterface::class),
            $config,
            new NullLogger(),
        );
    }

    public function testReturns503WhenNetBoxDisabled(): void
    {
        $controller = $this->createController(false);
        $request = new Request();

        $response = $controller->__invoke($request);

        $this->assertSame(503, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('nicht aktiviert', $data['error']);
    }

    public function testReturnsDeviceTypesWithIdAndModel(): void
    {
        $netBoxClient = $this->createMock(NetBoxClientInterface::class);
        $netBoxClient
            ->method('getDeviceTypes')
            ->willReturn([
                ['id' => 1, 'model' => 'PowerEdge R750', 'manufacturer' => ['id' => 1, 'name' => 'Dell']],
                ['id' => 2, 'model' => 'ProLiant DL380', 'manufacturer' => ['id' => 2, 'name' => 'HPE']],
            ]);

        $controller = $this->createController(true, $netBoxClient);
        $request = new Request(['tag' => 'rz']);

        $response = $controller->__invoke($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertEquals(['id' => 1, 'model' => 'PowerEdge R750'], $data[0]);
        $this->assertEquals(['id' => 2, 'model' => 'ProLiant DL380'], $data[1]);
    }

    public function testPassesTagQueryParameterToNetBoxClient(): void
    {
        $netBoxClient = $this->createMock(NetBoxClientInterface::class);
        $netBoxClient
            ->expects($this->once())
            ->method('getDeviceTypes')
            ->with('admin', $this->isType('string'))
            ->willReturn([['id' => 1, 'model' => 'ThinkPad X1']]);

        $controller = $this->createController(true, $netBoxClient);
        $request = new Request(['tag' => 'admin']);

        $response = $controller->__invoke($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testFallsBackToAllDeviceTypesWhenTagFilterReturnsEmpty(): void
    {
        $allDeviceTypes = [
            ['id' => 1, 'model' => 'server'],
        ];

        $netBoxClient = $this->createMock(NetBoxClientInterface::class);
        $netBoxClient
            ->expects($this->exactly(2))
            ->method('getDeviceTypes')
            ->willReturnCallback(function (string $tag) use ($allDeviceTypes): array {
                return $tag === '' ? $allDeviceTypes : [];
            });

        $controller = $this->createController(true, $netBoxClient);
        $request = new Request(['tag' => 'rz']);

        $response = $controller->__invoke($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals('server', $data[0]['model']);
    }

    public function testPassesEmptyTagWhenNoQueryParameterGiven(): void
    {
        $netBoxClient = $this->createMock(NetBoxClientInterface::class);
        $netBoxClient
            ->expects($this->once())
            ->method('getDeviceTypes')
            ->with('', $this->isType('string'))
            ->willReturn([]);

        $controller = $this->createController(true, $netBoxClient);
        $request = new Request();

        $response = $controller->__invoke($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testReturns503WhenNetBoxClientThrowsException(): void
    {
        $netBoxClient = $this->createMock(NetBoxClientInterface::class);
        $netBoxClient
            ->method('getDeviceTypes')
            ->willThrowException(new \RuntimeException('NetBox API error (HTTP 503)'));

        $controller = $this->createController(true, $netBoxClient);
        $request = new Request(['tag' => 'rz']);

        $response = $controller->__invoke($request);

        $this->assertSame(503, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('geladen werden', $data['error']);
    }

    public function testReturnsEmptyArrayWhenNoDeviceTypesFound(): void
    {
        $netBoxClient = $this->createMock(NetBoxClientInterface::class);
        $netBoxClient->method('getDeviceTypes')->willReturn([]);

        $controller = $this->createController(true, $netBoxClient);
        $request = new Request(['tag' => 'rz']);

        $response = $controller->__invoke($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertCount(0, $data);
    }
}
