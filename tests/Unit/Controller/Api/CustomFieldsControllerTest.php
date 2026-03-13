<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\CustomFieldsController;
use App\Domain\Repository\EvidenceConfigInterface;
use App\Domain\Repository\NetBoxClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

class CustomFieldsControllerTest extends TestCase
{
    private function createController(
        bool $netboxEnabled,
        ?NetBoxClientInterface $netBoxClient = null,
    ): CustomFieldsController {
        $config = $this->createMock(EvidenceConfigInterface::class);
        $config->method('isNetBoxEnabled')->willReturn($netboxEnabled);

        return new CustomFieldsController(
            $netBoxClient ?? $this->createMock(NetBoxClientInterface::class),
            $config,
            new NullLogger(),
        );
    }

    public function testReturns503WhenNetBoxDisabled(): void
    {
        $controller = $this->createController(false);
        $request = new Request();

        $response = $controller->__invoke($request, 'cf_nutzungstyp');

        $this->assertSame(503, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('nicht aktiviert', $data['error']);
    }

    public function testReturnsChoicesFromNetBoxClient(): void
    {
        $netBoxClient = $this->createMock(NetBoxClientInterface::class);
        $netBoxClient
            ->method('getCustomFieldChoices')
            // Field is named cf_nutzungstyp in NetBox – passed through as-is
            ->with('cf_nutzungstyp', $this->isType('string'))
            ->willReturn([
                ['id' => 1, 'label' => 'Produktiv'],
                ['id' => 2, 'label' => 'Test'],
            ]);

        $controller = $this->createController(true, $netBoxClient);
        $request = new Request();

        // Accept both prefixed and bare names
        $response1 = $controller->__invoke($request, 'cf_nutzungstyp');
        $this->assertSame(200, $response1->getStatusCode());
        $data1 = json_decode((string) $response1->getContent(), true);
        $this->assertArrayHasKey('choices', $data1);
        $this->assertCount(2, $data1['choices']);
        $this->assertEquals(['id' => 1, 'label' => 'Produktiv'], $data1['choices'][0]);

        $response2 = $controller->__invoke($request, 'cf_nutzungstyp');
        $this->assertSame(200, $response2->getStatusCode());
        $data2 = json_decode((string) $response2->getContent(), true);
        $this->assertEquals($data1, $data2, 'Both calls should return identical payload');
    }

    public function testHandlesNetBoxClientException(): void
    {
        $netBoxClient = $this->createMock(NetBoxClientInterface::class);
        $netBoxClient
            ->method('getCustomFieldChoices')
            ->willThrowException(new \RuntimeException('boom'));

        $controller = $this->createController(true, $netBoxClient);
        $request = new Request();

        $response = $controller->__invoke($request, 'cf_nutzungstyp');
        $this->assertSame(503, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }
}
