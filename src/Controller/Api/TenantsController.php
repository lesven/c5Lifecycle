<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\NetBox\NetBoxClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class TenantsController
{
    public function __construct(
        private readonly NetBoxClient $netBoxClient,
        private readonly EvidenceConfig $config,
        private readonly LoggerInterface $netboxLogger,
    ) {}

    #[Route('/api/tenants', name: 'api_tenants', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        if (!$this->config->isNetBoxEnabled()) {
            return new JsonResponse([]);
        }

        $requestId = Uuid::v4()->toRfc4122();

        try {
            $tenants = $this->netBoxClient->getTenants($requestId);
            $result = array_map(fn(array $t) => ['id' => $t['id'], 'name' => $t['name']], $tenants);
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->netboxLogger->error('Tenants fetch failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse([]);
        }
    }
}
