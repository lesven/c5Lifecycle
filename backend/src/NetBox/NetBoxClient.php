<?php
declare(strict_types=1);

namespace C5\NetBox;

use C5\Config;
use C5\Log\Logger;

class NetBoxClient
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Search for a device by asset_tag.
     * Returns the first matching device array or null.
     */
    public function findDeviceByAssetTag(string $assetTag, string $requestId): ?array
    {
        $response = $this->get('/api/dcim/devices/', ['asset_tag' => $assetTag], $requestId);
        if ($response === null) {
            return null;
        }
        $results = $response['results'] ?? [];
        return count($results) > 0 ? $results[0] : null;
    }

    /**
     * Update a device (status, custom fields, etc.) via PATCH.
     */
    public function updateDevice(int $deviceId, array $data, string $requestId): ?array
    {
        return $this->patch("/api/dcim/devices/{$deviceId}/", $data, $requestId);
    }

    /**
     * Create a journal entry for a device.
     */
    public function createJournalEntry(array $data, string $requestId): ?array
    {
        return $this->post('/api/extras/journal-entries/', $data, $requestId);
    }

    /**
     * Execute a GET request against NetBox API.
     */
    public function get(string $path, array $params, string $requestId): ?array
    {
        $url = $this->buildUrl($path, $params);
        return $this->request('GET', $url, null, $requestId);
    }

    /**
     * Execute a PATCH request against NetBox API.
     */
    public function patch(string $path, array $data, string $requestId): ?array
    {
        $url = $this->buildUrl($path);
        return $this->request('PATCH', $url, $data, $requestId);
    }

    /**
     * Execute a POST request against NetBox API.
     */
    public function post(string $path, array $data, string $requestId): ?array
    {
        $url = $this->buildUrl($path);
        return $this->request('POST', $url, $data, $requestId);
    }

    private function buildUrl(string $path, array $params = []): string
    {
        $baseUrl = rtrim($this->config->get('netbox.base_url', ''), '/');
        $url = $baseUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    private function request(string $method, string $url, ?array $data, string $requestId): ?array
    {
        $timeout = (int) $this->config->get('netbox.timeout', 10);
        $verifySsl = (bool) $this->config->get('netbox.verify_ssl', true);
        $token = $this->config->get('netbox.api_token', '');

        Logger::info("NetBox API {$method}", [
            'request_id' => $requestId,
            'url' => $url,
        ]);

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Token ' . $token,
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($data !== null) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'PATCH') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
            if ($data !== null) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("NetBox cURL error", [
                'request_id' => $requestId,
                'error' => $error,
            ]);
            throw new \RuntimeException('NetBox cURL error: ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            Logger::error("NetBox API error", [
                'request_id' => $requestId,
                'http_code' => $httpCode,
                'response' => $response,
            ]);
            throw new \RuntimeException("NetBox API error (HTTP {$httpCode}): " . ($response ?: 'no response'));
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            return null;
        }

        return $result;
    }
}
