<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SubmitControllerTest extends WebTestCase
{
    public function testSubmitWithUnknownEventTypeReturns404(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/submit/nonexistent', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['asset_id' => 'TEST']));

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Unbekannter Event-Typ', $data['error']);
    }

    public function testSubmitWithInvalidJsonReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/submit/rz_provision', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'not json');

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testSubmitWithMissingFieldsReturns422(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/submit/rz_provision', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['asset_id' => 'SRV-001']));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('fields', $data);
        $this->assertArrayHasKey('device_type', $data['fields']);
    }

    public function testSubmitConvertsDashToUnderscore(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/submit/rz-provision', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['asset_id' => 'SRV-001']));

        // Should not return 404 (event type recognized after dash→underscore)
        $this->assertResponseStatusCodeSame(422);
    }
}
