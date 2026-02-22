<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\NetBox\NetBoxClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ContactsController
{
    public function __construct(
        private readonly NetBoxClient $netBoxClient,
        private readonly EvidenceConfig $config,
        private readonly LoggerInterface $netboxLogger,
    ) {}

    #[Route('/api/contacts', name: 'api_contacts', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        if (!$this->config->isNetBoxEnabled()) {
            return new JsonResponse([]);
        }

        $requestId = Uuid::v4()->toRfc4122();

        try {
            $contacts = $this->netBoxClient->getContacts($requestId);
            $result = array_map(fn(array $c) => ['id' => $c['id'], 'name' => $c['name']], $contacts);
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->netboxLogger->error('Contacts fetch failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse([]);
        }
    }
}
