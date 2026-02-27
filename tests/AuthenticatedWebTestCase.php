<?php

declare(strict_types=1);

namespace App\Tests;

use App\Infrastructure\Persistence\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for integration tests that require authentication.
 */
abstract class AuthenticatedWebTestCase extends WebTestCase
{
    protected static function createAuthenticatedClient(string $role = 'ROLE_USER'): KernelBrowser
    {
        $client = static::createClient();

        self::ensureSchema($client);

        $user = self::createTestUser($client, $role);
        $client->loginUser($user);

        return $client;
    }

    protected static function createTestUser(KernelBrowser $client, string $role = 'ROLE_USER'): User
    {
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $email = $role === 'ROLE_ADMIN' ? 'admin@test.de' : 'user@test.de';
        $displayName = $role === 'ROLE_ADMIN' ? 'Test Admin' : 'Test User';
        $roles = $role === 'ROLE_ADMIN' ? ['ROLE_ADMIN'] : ['ROLE_USER'];

        // Check if user already exists
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing !== null) {
            return $existing;
        }

        $user = new User($email, $displayName, 'test-password', $roles);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected static function ensureSchema(KernelBrowser $client): void
    {
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        if (!empty($metadata)) {
            // For SQLite in-memory, always create fresh schema
            $schemaTool->createSchema($metadata);
        }
    }
}
