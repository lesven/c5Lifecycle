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
     * @return array<int, array<string, mixed>>
     */
    public function getContacts(string $requestId): array;

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
}
