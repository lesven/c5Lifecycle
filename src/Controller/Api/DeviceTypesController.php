<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\EvidenceConfigInterface;
use App\Domain\Repository\NetBoxClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class DeviceTypesController
{
    public function __construct(
        private readonly NetBoxClientInterface $netBoxClient,
        private readonly EvidenceConfigInterface $config,
        private readonly LoggerInterface $netboxLogger,
    ) {
    }

    #[Route('/api/device-types', name: 'api_device_types', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->config->isNetBoxEnabled()) {
            return new JsonResponse(
                ['error' => 'NetBox ist nicht aktiviert'],
                JsonResponse::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $tag = $request->query->getString('tag', '');
        $requestId = Uuid::v4()->toRfc4122();

        try {
            $deviceTypes = $this->netBoxClient->getDeviceTypes($tag, $requestId);

            // Fallback: if tag filter yields no results, load all device types.
            // This ensures the dropdown works even before NetBox tags are assigned.
            if ($tag !== '' && $deviceTypes === []) {
                $this->netboxLogger->info('Device types tag filter returned empty, retrying without tag', [
                    'request_id' => $requestId,
                    'tag' => $tag,
                ]);
                $deviceTypes = $this->netBoxClient->getDeviceTypes('', $requestId);
            }

            $result = array_map(
                fn (array $dt) => ['id' => $dt['id'], 'model' => $dt['model']],
                $deviceTypes,
            );
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->netboxLogger->error('Device types fetch failed', [
                'request_id' => $requestId,
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse(
                ['error' => 'Gerätetypen konnten nicht geladen werden'],
                JsonResponse::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
