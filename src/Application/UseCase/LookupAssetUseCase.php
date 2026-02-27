<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Service\DeviceTransformer;
use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\NetBox\NetBoxClient;
use Psr\Log\LoggerInterface;

final class LookupAssetUseCase
{
    public function __construct(
        private readonly NetBoxClient $netBoxClient,
        private readonly DeviceTransformer $deviceTransformer,
        private readonly EvidenceConfig $config,
        private readonly LoggerInterface $netboxLogger,
    ) {}

    public function execute(string $assetId): array
    {
        if ($assetId === '') {
            return ['found' => false, 'reason' => 'missing_asset_id'];
        }

        if (!$this->config->isNetBoxEnabled()) {
            return ['found' => false, 'reason' => 'netbox_disabled'];
        }

        try {
            $device = $this->netBoxClient->findDeviceByAssetTag($assetId, 'lookup-' . $assetId);

            if ($device === null) {
                return ['found' => false];
            }

            return $this->deviceTransformer->transform($device);
        } catch (\Throwable $e) {
            $this->netboxLogger->error('NetBox lookup failed', [
                'asset_id' => $assetId,
                'error' => $e->getMessage(),
            ]);
            return ['found' => false, 'reason' => 'netbox_error'];
        }
    }
}
