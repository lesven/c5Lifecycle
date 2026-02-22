<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\EvidenceSubmission;
use App\Domain\Service\CustomFieldMapper;
use App\Domain\Service\JournalBuilder;
use App\Domain\Service\StatusMapper;
use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\NetBox\NetBoxClient;
use Psr\Log\LoggerInterface;
use App\Domain\ValueObject\EventType;
use RuntimeException;

class SyncNetBoxUseCase
{
    public function __construct(
        private readonly NetBoxClient $netBoxClient,
        private readonly StatusMapper $statusMapper,
        private readonly CustomFieldMapper $customFieldMapper,
        private readonly JournalBuilder $journalBuilder,
        private readonly EvidenceConfig $config,
        private readonly LoggerInterface $netboxLogger,
    ) {}

    /**
     * @return array{synced: bool, status: string|null, error: string|null, error_trace: string|null}
     */
    public function execute(EvidenceSubmission $submission, string $emailBody, string $evidenceTo): array
    {
        $syncRule = $this->config->getNetBoxSyncRule($submission->eventType);

        if ($syncRule === 'none') {
            return ['synced' => false, 'status' => null, 'error' => null, 'error_trace' => null];
        }

        try {
            $result = $this->syncDevice($submission, $emailBody, $evidenceTo, $syncRule);
            return ['synced' => true, 'status' => $result['status'] ?? null, 'error' => null, 'error_trace' => null];
        } catch (\Throwable $e) {
            $error = sprintf('%s: %s', get_class($e), $e->getMessage());
            $trace = $this->config->isNetBoxDebug() ? $e->getTraceAsString() : null;

            $this->netboxLogger->error('NetBox sync failed', [
                'request_id' => $submission->requestId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            if ($this->config->getNetBoxOnError() === 'fail') {
                throw $e;
            }

            return ['synced' => false, 'status' => null, 'error' => $error, 'error_trace' => $trace];
        }
    }

    private function syncDevice(EvidenceSubmission $submission, string $emailBody, string $evidenceTo, string $syncRule): array
    {
        $data = $submission->data;
        $eventType = $submission->eventType;
        $requestId = $submission->requestId;
        $assetId = $data['asset_id'] ?? '';
        $result = ['status' => null];

        $device = $this->netBoxClient->findDeviceByAssetTag($assetId, $requestId);
        if ($device === null) {
            $eventTypeEnum = EventType::tryFrom($eventType);
            if ($eventTypeEnum?->isProvisionEvent() && $this->config->isCreateOnProvision()) {
                $device = $this->createNetBoxDevice($eventType, $data, $requestId);
            } else {
                throw new RuntimeException('Asset nicht in NetBox gefunden');
            }
        }

        $deviceId = (int) $device['id'];

        $patchData = ['comments' => $emailBody];

        $tenantId = isset($data['tenant_id']) && $data['tenant_id'] !== '' ? (int) $data['tenant_id'] : 0;
        if ($tenantId > 0) {
            $patchData['tenant'] = $tenantId;
        }

        if ($syncRule === 'update_status') {
            $targetStatus = $this->statusMapper->getTargetStatus($eventType);
            if ($targetStatus !== null) {
                $patchData['status'] = $targetStatus;
                $result['status'] = $targetStatus;
            }

            $customFields = $this->customFieldMapper->map($eventType, $data);
            if (!empty($customFields)) {
                $patchData['custom_fields'] = $customFields;
            }
        }

        $this->netBoxClient->updateDevice($deviceId, $patchData, $requestId);

        // Contact assignment (idempotent: nur anlegen wenn noch nicht vorhanden)
        $contactId = isset($data['contact_id']) && $data['contact_id'] !== '' ? (int) $data['contact_id'] : 0;
        if ($contactId > 0) {
            $contactRoleId = $this->resolveContactRoleId();
            $existing = $this->netBoxClient->findContactAssignment($deviceId, $contactId, $contactRoleId, $requestId);
            if ($existing === null) {
                $this->netBoxClient->createContactAssignment($deviceId, $contactId, $contactRoleId, $requestId);
            }
        }

        // Journal entry
        $kind = $this->statusMapper->getJournalKind($eventType);
        $comments = $this->journalBuilder->build($eventType, $submission->eventMeta, $data, $requestId, $evidenceTo);

        $this->netBoxClient->createJournalEntry([
            'assigned_object_type' => 'dcim.device',
            'assigned_object_id' => $deviceId,
            'kind' => $kind,
            'comments' => $comments,
        ], $requestId);

        if ($result['status'] === null && $syncRule === 'journal_only') {
            $result['status'] = 'journal_created';
        }

        return $result;
    }

    private function createNetBoxDevice(string $eventType, array $data, string $requestId): array
    {
        $manufacturer = trim((string) ($data['manufacturer'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $serialNumber = trim((string) ($data['serial_number'] ?? ''));
        $assetTag = trim((string) ($data['asset_id'] ?? ''));

        $deviceTypeId = 0;
        if ($model !== '') {
            $deviceType = $this->netBoxClient->findDeviceTypeByModel($manufacturer, $model, $requestId);
            if ($deviceType !== null) {
                $deviceTypeId = (int) $deviceType['id'];
            }
        }

        $defaults = $this->config->getProvisionDefaults();
        if ($deviceTypeId === 0) {
            $deviceTypeId = $defaults['device_type_id'];
        }
        $siteId = $defaults['site_id'];
        $roleId = $defaults['role_id'];

        if ($deviceTypeId === 0 || $siteId === 0 || $roleId === 0) {
            throw new RuntimeException(
                'NetBox Device-Anlage fehlgeschlagen: Pflicht-IDs fehlen in netbox.provision_defaults ' .
                "(device_type_id={$deviceTypeId}, site_id={$siteId}, role_id={$roleId})"
            );
        }

        $postData = [
            'name' => $assetTag,
            'device_type' => $deviceTypeId,
            'role' => $roleId,
            'site' => $siteId,
            'status' => $this->statusMapper->getTargetStatus($eventType) ?? 'active',
            'asset_tag' => $assetTag,
        ];

        if ($serialNumber !== '') {
            $postData['serial'] = $serialNumber;
        }

        $tenantId = isset($data['tenant_id']) && $data['tenant_id'] !== '' ? (int) $data['tenant_id'] : 0;
        if ($tenantId > 0) {
            $postData['tenant'] = $tenantId;
        }

        $customFields = $this->customFieldMapper->map($eventType, $data);
        if (!empty($customFields)) {
            $postData['custom_fields'] = $customFields;
        }

        $this->netboxLogger->info('NetBox Device-Anlage', [
            'request_id' => $requestId,
            'asset_tag' => $assetTag,
            'event_type' => $eventType,
        ]);

        return $this->netBoxClient->createDevice($postData, $requestId);
    }

    private function resolveContactRoleId(): int
    {
        $raw = $this->config->getContactRoleOwner();
        if ($raw === '') {
            return 0;
        }
        if (ctype_digit($raw)) {
            return (int) $raw;
        }
        if (preg_match('/(\d+)\/?$/', $raw, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
