<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Entity;

use App\Infrastructure\Persistence\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testConstructorSetsFields(): void
    {
        $user = new User('test@example.de', 'Max Muster', 'hashed-pw', ['ROLE_USER']);

        $this->assertSame('test@example.de', $user->getEmail());
        $this->assertSame('Max Muster', $user->getDisplayName());
        $this->assertSame('hashed-pw', $user->getPassword());
        $this->assertTrue($user->isActive());
        $this->assertNotNull($user->getCreatedAt());
        $this->assertNull($user->getUpdatedAt());
        $this->assertNull($user->getId());
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User('admin@firma.de', 'Admin', 'pw', ['ROLE_ADMIN']);

        $this->assertSame('admin@firma.de', $user->getUserIdentifier());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User('admin@firma.de', 'Admin', 'pw', ['ROLE_ADMIN']);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testDefaultRoleUser(): void
    {
        $user = new User('user@firma.de', 'User', 'pw');

        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertFalse($user->isAdmin());
    }

    public function testIsAdminReturnsTrueForAdminRole(): void
    {
        $user = new User('admin@firma.de', 'Admin', 'pw', ['ROLE_ADMIN']);

        $this->assertTrue($user->isAdmin());
    }

    public function testIsAdminReturnsFalseForUserRole(): void
    {
        $user = new User('user@firma.de', 'User', 'pw', ['ROLE_USER']);

        $this->assertFalse($user->isAdmin());
    }

    public function testSetEmailUpdatesTimestamp(): void
    {
        $user = new User('old@firma.de', 'User', 'pw');
        $this->assertNull($user->getUpdatedAt());

        $user->setEmail('new@firma.de');
        $this->assertSame('new@firma.de', $user->getEmail());
        $this->assertNotNull($user->getUpdatedAt());
    }

    public function testSetDisplayNameUpdatesTimestamp(): void
    {
        $user = new User('test@firma.de', 'Alt', 'pw');

        $user->setDisplayName('Neu');
        $this->assertSame('Neu', $user->getDisplayName());
        $this->assertNotNull($user->getUpdatedAt());
    }

    public function testSetActiveUpdatesTimestamp(): void
    {
        $user = new User('test@firma.de', 'User', 'pw');
        $this->assertTrue($user->isActive());

        $user->setActive(false);
        $this->assertFalse($user->isActive());
        $this->assertNotNull($user->getUpdatedAt());
    }

    public function testSetPasswordUpdatesTimestamp(): void
    {
        $user = new User('test@firma.de', 'User', 'pw');

        $user->setPassword('new-hashed-pw');
        $this->assertSame('new-hashed-pw', $user->getPassword());
        $this->assertNotNull($user->getUpdatedAt());
    }

    public function testSetRolesUpdatesTimestamp(): void
    {
        $user = new User('test@firma.de', 'User', 'pw');

        $user->setRoles(['ROLE_ADMIN']);
        $this->assertTrue($user->isAdmin());
        $this->assertNotNull($user->getUpdatedAt());
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $user = new User('test@firma.de', 'User', 'pw');
        $user->eraseCredentials();

        // Password should remain unchanged
        $this->assertSame('pw', $user->getPassword());
    }
}
