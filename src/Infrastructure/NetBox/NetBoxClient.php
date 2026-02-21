<?php

declare(strict_types=1);

namespace App\Infrastructure\NetBox;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NetBoxClient
{
    public function __construct(
        private readonly HttpClientInterface $netboxClient,
        private readonly LoggerInterface $netboxLogger,
    ) {}

    public function findDeviceByAssetTag(string $assetTag, string $requestId): ?array
    {
        $response = $this->get('/api/dcim/devices/', ['asset_tag' => $assetTag], $requestId);
        if ($response === null) {
            return null;
        }
        $results = $response['results'] ?? [];
        return count($results) > 0 ? $results[0] : null;
    }

    public function updateDevice(int $deviceId, array $data, string $requestId): ?array
    {
        return $this->patch("/api/dcim/devices/{$deviceId}/", $data, $requestId);
    }

    public function createJournalEntry(array $data, string $requestId): ?array
    {
        return $this->post('/api/extras/journal-entries/', $data, $requestId);
    }

    public function findDeviceTypeByModel(string $manufacturer, string $model, string $requestId): ?array
    {
        $params = ['model' => $model];
        if ($manufacturer !== '') {
            $params['manufacturer__name'] = $manufacturer;
        }
        $response = $this->get('/api/dcim/device-types/', $params, $requestId);
        $results = $response['results'] ?? [];
        return count($results) > 0 ? $results[0] : null;
    }

    public function createDevice(array $data, string $requestId): array
    {
        $result = $this->post('/api/dcim/devices/', $data, $requestId);
        if ($result === null) {
            throw new \RuntimeException('NetBox Device-Erstellung lieferte kein Ergebnis');
        }
        return $result;
    }

    public function getTenants(string $requestId): array
    {
        $response = $this->get('/api/tenancy/tenants/', ['ordering' => 'name', 'limit' => 1000], $requestId);
        return $response['results'] ?? [];
    }

    public function getContacts(string $requestId): array
    {
        $response = $this->get('/api/tenancy/contacts/', ['ordering' => 'name', 'limit' => 1000], $requestId);
        return $response['results'] ?? [];
    }

    public function findContactAssignment(int $deviceId, int $contactId, int $roleId, string $requestId): ?array
    {
        $params = [
            'object_type' => 'dcim.device',
            'object_id' => (string) $deviceId,
            'contact_id' => (string) $contactId,
        ];
        if ($roleId > 0) {
            $params['role_id'] = (string) $roleId;
        }
        $response = $this->get('/api/tenancy/contact-assignments/', $params, $requestId);
        $results = $response['results'] ?? [];
        return count($results) > 0 ? $results[0] : null;
    }

    public function createContactAssignment(int $deviceId, int $contactId, int $roleId, string $requestId): ?array
    {
        $data = [
            'object_type' => 'dcim.device',
            'object_id' => $deviceId,
            'contact' => $contactId,
        ];
        if ($roleId > 0) {
            $data['role'] = $roleId;
        }
        return $this->post('/api/tenancy/contact-assignments/', $data, $requestId);
    }

    private function get(string $path, array $params, string $requestId): ?array
    {
        $this->netboxLogger->info('NetBox API GET', ['request_id' => $requestId, 'path' => $path]);

        $response = $this->netboxClient->request('GET', $path, [
            'query' => $params,
        ]);

        return $this->handleResponse($response, $requestId);
    }

    private function patch(string $path, array $data, string $requestId): ?array
    {
        $this->netboxLogger->info('NetBox API PATCH', ['request_id' => $requestId, 'path' => $path]);

        $response = $this->netboxClient->request('PATCH', $path, [
            'json' => $data,
        ]);

        return $this->handleResponse($response, $requestId);
    }

    private function post(string $path, array $data, string $requestId): ?array
    {
        $this->netboxLogger->info('NetBox API POST', ['request_id' => $requestId, 'path' => $path]);

        $response = $this->netboxClient->request('POST', $path, [
            'json' => $data,
        ]);

        return $this->handleResponse($response, $requestId);
    }

    private function handleResponse(\Symfony\Contracts\HttpClient\ResponseInterface $response, string $requestId): ?array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = $response->getContent(false);
            $this->netboxLogger->error('NetBox API error', [
                'request_id' => $requestId,
                'http_code' => $statusCode,
                'response' => $body,
            ]);
            throw new \RuntimeException("NetBox API error (HTTP {$statusCode}): " . ($body ?: 'no response'));
        }

        $content = $response->getContent();
        $result = json_decode($content, true);

        return is_array($result) ? $result : null;
    }
}
