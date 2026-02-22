<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ok', $data['status']);
    }

    public function testHealthEndpointHasRequestIdHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertTrue($client->getResponse()->headers->has('X-Request-ID'));
    }
}
