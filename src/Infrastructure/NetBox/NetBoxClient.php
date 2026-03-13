<?php

declare(strict_types=1);

namespace App\Infrastructure\NetBox;

use App\Domain\Repository\NetBoxClientInterface;
use App\Infrastructure\Http\ApiResponseParser;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class NetBoxClient implements NetBoxClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $netboxClient,
        private readonly LoggerInterface $netboxLogger,
    ) {
    }

    public function findDeviceByAssetTag(string $assetTag, string $requestId): ?array
    {
        $response = $this->get('/api/dcim/devices/', ['asset_tag' => $assetTag], $requestId);
        if ($response === null) {
            return null;
        }
        $results = $response['results'] ?? [];
        return count($results) > 0 ? $results[0] : null;
    }

    public function findDeviceById(int $deviceId, string $requestId): ?array
    {
        $response = $this->get("/api/dcim/devices/{$deviceId}/", [], $requestId);
        return $response;
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
            throw new RuntimeException('NetBox Device-Erstellung lieferte kein Ergebnis');
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

    public function getRegions(string $requestId): array
    {
        $response = $this->get('/api/dcim/regions/', ['ordering' => 'name', 'limit' => 1000], $requestId);
        return $response['results'] ?? [];
    }

    public function getSiteGroups(string $requestId): array
    {
        $response = $this->get('/api/dcim/site-groups/', ['ordering' => 'name', 'limit' => 1000], $requestId);
        return $response['results'] ?? [];
    }

    public function getSites(string $requestId): array
    {
        $response = $this->get('/api/dcim/sites/', ['ordering' => 'name', 'limit' => 1000], $requestId);
        return $response['results'] ?? [];
    }

    public function getDeviceTypes(string $tag, string $requestId): array
    {
        $params = ['ordering' => 'model', 'limit' => 1000];
        if ($tag !== '') {
            $params['tag'] = $tag;
        }
        $response = $this->get('/api/dcim/device-types/', $params, $requestId);
        return $response['results'] ?? [];
    }

    public function getOwnerContactAssignments(int $roleId, string $requestId): array
    {
        $params = [
            'object_type' => 'dcim.device',
            'limit' => 1000,
        ];
        if ($roleId > 0) {
            $params['role_id'] = (string) $roleId;
        }
        $response = $this->get('/api/tenancy/contact-assignments/', $params, $requestId);
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

    /**
     * Retrieve human-readable name for a contact by ID.
     * Returns null if contact not found, with fallback to ID string.
     * @return string|null The contact name or null if not found
     */
    public function getContactNameById(int $contactId, string $requestId): ?string
    {
        try {
            $response = $this->get("/api/tenancy/contacts/{$contactId}/", [], $requestId);
            if ($response === null) {
                $this->netboxLogger->warning('Contact not found', ['contact_id' => $contactId, 'request_id' => $requestId]);
                return null;
            }
            return $response['name'] ?? null;
        } catch (RuntimeException $e) {
            $this->netboxLogger->warning('Error fetching contact name', ['contact_id' => $contactId, 'error' => $e->getMessage(), 'request_id' => $requestId]);
            return null;
        }
    }

    /**
     * Retrieve human-readable name for a device type by ID.
     * Returns null if device type not found.
     */
    public function getDeviceTypeNameById(int $deviceTypeId, string $requestId): ?string
    {
        try {
            $response = $this->get("/api/dcim/device-types/{$deviceTypeId}/", [], $requestId);
            if ($response === null) {
                $this->netboxLogger->warning('Device type not found', ['device_type_id' => $deviceTypeId, 'request_id' => $requestId]);
                return null;
            }
            // NetBox device types return 'model', sometimes with 'manufacturer' nested
            $model = $response['model'] ?? null;
            if ($model === null) {
                return null;
            }
            if (!empty($response['manufacturer']['name'])) {
                return $response['manufacturer']['name'] . ' ' . $model;
            }
            return $model;
        } catch (RuntimeException $e) {
            $this->netboxLogger->warning('Error fetching device type name', ['device_type_id' => $deviceTypeId, 'error' => $e->getMessage(), 'request_id' => $requestId]);
            return null;
        }
    }

    /**
     * Retrieve human-readable name for a site by ID.
     */
    public function getSiteNameById(int $siteId, string $requestId): ?string
    {
        try {
            $response = $this->get("/api/dcim/sites/{$siteId}/", [], $requestId);
            if ($response === null) {
                $this->netboxLogger->warning('Site not found', ['site_id' => $siteId, 'request_id' => $requestId]);
                return null;
            }
            return $response['name'] ?? null;
        } catch (RuntimeException $e) {
            $this->netboxLogger->warning('Error fetching site name', ['site_id' => $siteId, 'error' => $e->getMessage(), 'request_id' => $requestId]);
            return null;
        }
    }

    /**
     * Retrieve human-readable name for a site group by ID.
     */
    public function getSiteGroupNameById(int $siteGroupId, string $requestId): ?string
    {
        try {
            $response = $this->get("/api/dcim/site-groups/{$siteGroupId}/", [], $requestId);
            if ($response === null) {
                $this->netboxLogger->warning('Site group not found', ['site_group_id' => $siteGroupId, 'request_id' => $requestId]);
                return null;
            }
            return $response['name'] ?? null;
        } catch (RuntimeException $e) {
            $this->netboxLogger->warning('Error fetching site group name', ['site_group_id' => $siteGroupId, 'error' => $e->getMessage(), 'request_id' => $requestId]);
            return null;
        }
    }

    /**
     * Retrieve human-readable name for a region by ID.
     */
    public function getRegionNameById(int $regionId, string $requestId): ?string
    {
        try {
            $response = $this->get("/api/dcim/regions/{$regionId}/", [], $requestId);
            if ($response === null) {
                $this->netboxLogger->warning('Region not found', ['region_id' => $regionId, 'request_id' => $requestId]);
                return null;
            }
            return $response['name'] ?? null;
        } catch (RuntimeException $e) {
            $this->netboxLogger->warning('Error fetching region name', ['region_id' => $regionId, 'error' => $e->getMessage(), 'request_id' => $requestId]);
            return null;
        }
    }

    /**
     * Retrieve human-readable name for a tenant by ID.
     */
    public function getTenantNameById(int $tenantId, string $requestId): ?string
    {
        try {
            $response = $this->get("/api/tenancy/tenants/{$tenantId}/", [], $requestId);
            if ($response === null) {
                $this->netboxLogger->warning('Tenant not found', ['tenant_id' => $tenantId, 'request_id' => $requestId]);
                return null;
            }
            return $response['name'] ?? null;
        } catch (RuntimeException $e) {
            $this->netboxLogger->warning('Error fetching tenant name', ['tenant_id' => $tenantId, 'error' => $e->getMessage(), 'request_id' => $requestId]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomFieldChoices(string $fieldName, string $requestId): array
    {
        // search by name; the API returns a paginated list but name is unique
        $response = $this->get('/api/extras/custom-fields/', ['name' => $fieldName], $requestId);
        $results = $response['results'] ?? [];
        if (count($results) === 0) {
            return [];
        }

        // The custom-fields list endpoint only returns choice_set metadata (id, name,
        // choices_count) – not the actual choices.  We need a second call to the
        // choice-sets endpoint to fetch the real extra_choices array.
        $choiceSetId = $results[0]['choice_set']['id'] ?? null;
        if ($choiceSetId === null) {
            return [];
        }

        $choiceSet = $this->get("/api/extras/custom-field-choice-sets/{$choiceSetId}/", [], $requestId);
        if ($choiceSet === null) {
            return [];
        }

        // extra_choices is an array of [value, label] tuples (both strings)
        $extraChoices = $choiceSet['extra_choices'] ?? [];
        $mapped = [];
        foreach ($extraChoices as $i => $pair) {
            $mapped[] = [
                'id'    => $i,
                'label' => $pair[1] ?? $pair[0] ?? '',
            ];
        }
        return $mapped;
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

    private function handleResponse(ResponseInterface $response, string $requestId): ?array
    {
        return ApiResponseParser::parse($response, 'NetBox', $requestId, $this->netboxLogger);
    }
}
