<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\UseCase\LookupAssetUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AssetLookupController
{
    public function __construct(
        private readonly LookupAssetUseCase $lookupAsset,
    ) {}

    #[Route('/api/asset-lookup', name: 'api_asset_lookup', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $assetId = $request->query->getString('asset_id', '');
        $result = $this->lookupAsset->execute($assetId);

        return new JsonResponse($result);
    }
}
