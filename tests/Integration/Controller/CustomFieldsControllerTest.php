<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Tests\AuthenticatedWebTestCase;

class CustomFieldsControllerTest extends AuthenticatedWebTestCase
{
    public function testEndpointExistsAndReturnsStructuredJson(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/custom-fields/cf_nutzungstyp');
        $status = $client->getResponse()->getStatusCode();
        // depending on config and network the endpoint may either succeed (200) or
        // report that NetBox is unavailable (503). Both are acceptable for an
        // existence check.
        $this->assertTrue(in_array($status, [200, 503]), "Unexpected status $status");

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertTrue(isset($data['choices']) || isset($data['error']));
    }
}
