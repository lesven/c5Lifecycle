<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Tests\AuthenticatedWebTestCase;

class SecurityControllerTest extends AuthenticatedWebTestCase
{
    public function testLoginPageRendersSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#input-email');
        $this->assertSelectorExists('#input-password');
    }

    public function testLoginPageHasSubmitButton(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.btn-login', 'Anmelden');
    }

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects('/login');
    }

    public function testUnauthenticatedUserCannotAccessForms(): void
    {
        $client = static::createClient();
        $client->request('GET', '/forms/rz-provision');

        $this->assertResponseRedirects('/login');
    }

    public function testUnauthenticatedUserCannotSubmit(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/submit/rz_provision', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['asset_id' => 'TEST']));

        // Should redirect to login (302) since not authenticated
        $this->assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserCanAccessIndex(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testLoggedInUserRedirectedFromLogin(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/login');

        $this->assertResponseRedirects('/');
    }

    public function testNonAdminCannotAccessAdminArea(): void
    {
        $client = static::createAuthenticatedClient('ROLE_USER');
        $client->request('GET', '/admin/users');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessAdminArea(): void
    {
        $client = static::createAuthenticatedClient('ROLE_ADMIN');
        $client->request('GET', '/admin/users');

        $this->assertResponseIsSuccessful();
    }

    public function testHealthEndpointRemainsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
    }
}
