<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\NetBox\NetBoxClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class LocationsController
{
    public function __construct(
        private readonly NetBoxClient $netBoxClient,
        private readonly EvidenceConfig $config,
        private readonly LoggerInterface $netboxLogger,
    ) {
    }

    #[Route('/api/locations/regions', name: 'api_locations_regions', methods: ['GET'])]
    public function regions(): JsonResponse
    {
        if (!$this->config->isNetBoxEnabled()) {
            return new JsonResponse(['error' => 'NetBox nicht aktiviert'], 503);
        }

        $requestId = Uuid::v4()->toRfc4122();

        try {
            $items = $this->netBoxClient->getRegions($requestId);
            $result = array_map(
                fn (array $r) => [
                    'id' => $r['id'],
                    'name' => $r['name'],
                    'slug' => $r['slug'] ?? '',
                    'parent_id' => $r['parent']['id'] ?? null,
                ],
                $items
            );
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->netboxLogger->error('Regions fetch failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse(['error' => 'NetBox nicht erreichbar'], 503);
        }
    }

    #[Route('/api/locations/site-groups', name: 'api_locations_site_groups', methods: ['GET'])]
    public function siteGroups(): JsonResponse
    {
        if (!$this->config->isNetBoxEnabled()) {
            return new JsonResponse(['error' => 'NetBox nicht aktiviert'], 503);
        }

        $requestId = Uuid::v4()->toRfc4122();

        try {
            $items = $this->netBoxClient->getSiteGroups($requestId);
            $result = array_map(
                fn (array $g) => [
                    'id' => $g['id'],
                    'name' => $g['name'],
                    'slug' => $g['slug'] ?? '',
                    'parent_id' => $g['parent']['id'] ?? null,
                ],
                $items
            );
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->netboxLogger->error('Site-groups fetch failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse(['error' => 'NetBox nicht erreichbar'], 503);
        }
    }

    #[Route('/api/locations/sites', name: 'api_locations_sites', methods: ['GET'])]
    public function sites(): JsonResponse
    {
        if (!$this->config->isNetBoxEnabled()) {
            return new JsonResponse(['error' => 'NetBox nicht aktiviert'], 503);
        }

        $requestId = Uuid::v4()->toRfc4122();

        try {
            $items = $this->netBoxClient->getSites($requestId);
            $result = array_map(
                fn (array $s) => [
                    'id' => $s['id'],
                    'name' => $s['name'],
                    'slug' => $s['slug'] ?? '',
                    'region_id' => $s['region']['id'] ?? null,
                    'site_group_id' => $s['group']['id'] ?? null,
                ],
                $items
            );
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->netboxLogger->error('Sites fetch failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse(['error' => 'NetBox nicht erreichbar'], 503);
        }
    }
}
