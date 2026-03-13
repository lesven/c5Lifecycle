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

/**
 * Exposes a tiny helper endpoint for populating form dropdowns with the
 * choices from a NetBox custom field.  This is intentionally narrow; the
 * frontend only needs to load the "cf_nutzungstyp" set today.
 */
final class CustomFieldsController
{
    public function __construct(
        private readonly NetBoxClientInterface $netBoxClient,
        private readonly EvidenceConfigInterface $config,
        private readonly LoggerInterface $netboxLogger,
    ) {
    }

    #[Route('/api/custom-fields/{fieldName}', name: 'api_custom_fields', methods: ['GET'])]
    public function __invoke(Request $request, string $fieldName): JsonResponse
    {
        if (!$this->config->isNetBoxEnabled()) {
            return new JsonResponse(
                ['error' => 'NetBox ist nicht aktiviert'],
                JsonResponse::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $requestId = Uuid::v4()->toRfc4122();
        // The frontend passes the field name as-is (e.g. "cf_nutzungstyp");
        // NetBox stores the field under the same name – no prefix transformation needed.
        $queryName = $fieldName;

        try {
            $choices = $this->netBoxClient->getCustomFieldChoices($queryName, $requestId);
            return new JsonResponse(['choices' => $choices]);
        } catch (\Throwable $e) {
            $this->netboxLogger->error('Custom field fetch failed', [
                'request_id' => $requestId,
                'field' => $fieldName,
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse(
                ['error' => 'Custom field konnte nicht geladen werden'],
                JsonResponse::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
