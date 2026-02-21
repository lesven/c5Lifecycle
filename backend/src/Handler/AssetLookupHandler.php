<?php
declare(strict_types=1);

namespace C5\Handler;

use C5\Config;
use C5\Log\Logger;
use C5\NetBox\NetBoxClient;
use C5\NetBox\DeviceTransformer;

class AssetLookupHandler
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        $assetId = $_GET['asset_id'] ?? '';

        if ($assetId === '') {
            $this->respond(400, ['found' => false, 'reason' => 'missing_asset_id']);
            return;
        }

        if (!$this->config->isNetBoxEnabled()) {
            $this->respond(200, ['found' => false, 'reason' => 'netbox_disabled']);
            return;
        }

        try {
            $client = new NetBoxClient($this->config);
            $device = $client->findDeviceByAssetTag($assetId, 'lookup-' . $assetId);

            if ($device === null) {
                $this->respond(200, ['found' => false]);
                return;
            }

            $result = DeviceTransformer::transform($device);
            $this->respond(200, $result);
        } catch (\Throwable $e) {
            Logger::error("NetBox lookup failed", [
                'asset_id' => $assetId,
                'error' => $e->getMessage(),
            ]);
            $this->respond(200, ['found' => false, 'reason' => 'netbox_error']);
        }
    }

    private function respond(int $status, array $data): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
