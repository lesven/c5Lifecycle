<?php

declare(strict_types=1);

namespace App\Domain\Repository;

/**
 * Client for interacting with the NetBox DCIM/IPAM system.
 *
 * Provides device lookup, creation, update, and journal entry capabilities.
 */
interface NetBoxClientInterface
{
    /**
     * Find a device by its asset tag.
     *
     * @return array<string, mixed>|null Device data or null if not found
     */
    public function findDeviceByAssetTag(string $assetTag, string $requestId): ?array;

    /**
     * Find a device by its NetBox ID.
     *
     * @return array<string, mixed>|null Device data or null if not found
     */
    public function findDeviceById(int $deviceId, string $requestId): ?array;

    /**
     * Update a device by its ID.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateDevice(int $deviceId, array $data, string $requestId): ?array;

    /**
     * Create a journal entry.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function createJournalEntry(array $data, string $requestId): ?array;

    /**
     * Find a device type by manufacturer and model.
     *
     * @return array<string, mixed>|null
     */
    public function findDeviceTypeByModel(string $manufacturer, string $model, string $requestId): ?array;

    /**
     * Create a new device.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws \RuntimeException if creation fails
     */
    public function createDevice(array $data, string $requestId): array;

    /**
     * Get all tenants.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTenants(string $requestId): array;

    /**
     * Get all contacts.
     *
     * Each entry contains at least: id, name, email (string, may be empty).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getContacts(string $requestId): array;

    /**
     * Get all contact assignments with the given role for dcim.device objects.
     *
     * Each entry contains: contact (id, name, email), object (id, name, asset_tag, url).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOwnerContactAssignments(int $roleId, string $requestId): array;

    /**
     * Find an existing contact assignment for a device.
     *
     * @return array<string, mixed>|null
     */
    public function findContactAssignment(int $deviceId, int $contactId, int $roleId, string $requestId): ?array;

    /**
     * Create a contact assignment for a device.
     *
     * @return array<string, mixed>|null
     */
    public function createContactAssignment(int $deviceId, int $contactId, int $roleId, string $requestId): ?array;

    /**
     * Get all regions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRegions(string $requestId): array;

    /**
     * Get all site groups.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSiteGroups(string $requestId): array;

    /**
     * Get all sites.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSites(string $requestId): array;

    /**
     * Get device types, optionally filtered by a NetBox tag slug.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDeviceTypes(string $tag, string $requestId): array;

    /**
     * Get the name of a contact by its ID.
     *
     * @return string|null The contact name, or null if not found or on error
     */
    public function getContactNameById(int $contactId, string $requestId): ?string;

    /**
     * Get the name of a device type by its ID.
     *
     * Returns formatted name as "Manufacturer Model" if manufacturer present, otherwise just model.
     *
     * @return string|null The device type name, or null if not found or on error
     */
    public function getDeviceTypeNameById(int $deviceTypeId, string $requestId): ?string;

    /**
     * Get the name of a site by its ID.
     *
     * @return string|null The site name, or null if not found or on error
     */
    public function getSiteNameById(int $siteId, string $requestId): ?string;

    /**
     * Get the name of a site group by its ID.
     *
     * @return string|null The site group name, or null if not found or on error
     */
    public function getSiteGroupNameById(int $siteGroupId, string $requestId): ?string;

    /**
     * Get the name of a region by its ID.
     *
     * @return string|null The region name, or null if not found or on error
     */
    public function getRegionNameById(int $regionId, string $requestId): ?string;

    /**
     * Get the name of a tenant by its ID.
     *
     * @return string|null The tenant name, or null if not found or on error
     */
    public function getTenantNameById(int $tenantId, string $requestId): ?string;

    /**
     * Retrieve the choice list for a given NetBox custom field name.
     *
     * The client will search the `/api/extras/custom-fields/` endpoint by name
     * and return the `choices` array from the first match. Each choice is
     * normalized to an array containing `id` and human-readable `label` so that
     * callers can render a dropdown without knowing the NetBox response format.
     *
     * @return array<int,array{id:int,label:string}>
     */
    public function getCustomFieldChoices(string $fieldName, string $requestId): array;
}
