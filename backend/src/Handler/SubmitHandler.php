<?php
declare(strict_types=1);

namespace C5\Handler;

use C5\Config;
use C5\EventRegistry;
use C5\Mail\MailBuilder;
use C5\Mail\MailSender;
use C5\Jira\JiraClient;
use C5\Log\Logger;
use C5\NetBox\NetBoxClient;
use C5\NetBox\StatusMapper;
use C5\NetBox\JournalBuilder;
use C5\NetBox\CustomFieldMapper;
use Ramsey\Uuid\Uuid;

class SubmitHandler
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(string $eventType): void
    {
        $requestId = Uuid::uuid4()->toString();
        Logger::info("Submit request", ['request_id' => $requestId, 'event' => $eventType]);

        // 1. Validate event type
        if (!EventRegistry::exists($eventType)) {
            $this->respond(404, [
                'error' => "Unbekannter Event-Typ: {$eventType}",
                'request_id' => $requestId,
            ]);
            return;
        }

        // 2. Parse JSON body
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->respond(400, [
                'error' => 'Ungültiger JSON-Body',
                'request_id' => $requestId,
            ]);
            return;
        }

        // 3. Server-side validation
        $event = EventRegistry::get($eventType);
        $errors = $this->validate($eventType, $event, $data);
        if (!empty($errors)) {
            Logger::warning("Validation failed", ['request_id' => $requestId, 'errors' => $errors]);
            $this->respond(422, [
                'error' => 'Validation failed',
                'fields' => $errors,
                'request_id' => $requestId,
            ]);
            return;
        }

        $assetId = $data['asset_id'] ?? 'UNKNOWN';

        // 4. Build and send evidence email
        $subject = EventRegistry::buildSubject($eventType, $assetId);
        $body = MailBuilder::build($event, $data, $requestId);
        $recipients = $this->config->getEvidenceRecipients($event['track']);

        try {
            $sender = new MailSender($this->config);
            $sender->send($recipients, $subject, $body, $requestId);
            Logger::info("Mail sent", ['request_id' => $requestId, 'to' => $recipients['to']]);
        } catch (\Throwable $e) {
            Logger::error("Mail failed", ['request_id' => $requestId, 'error' => $e->getMessage()]);
            $this->respond(502, [
                'error' => 'Evidence-Mail konnte nicht versendet werden: !!!'. $e->getMessage(),
                'detail' => $e->getMessage(),
                'request_id' => $requestId,
            ]);
            return;
        }

        // 5. Optional Jira ticket
        $jiraTicket = null;
        $jiraRule = $this->config->getJiraRule($eventType);

        if ($jiraRule !== 'none') {
            try {
                $jira = new JiraClient($this->config);
                $jiraTicket = $jira->createTicket($event, $data, $requestId);
                Logger::info("Jira ticket created", ['request_id' => $requestId, 'ticket' => $jiraTicket]);
            } catch (\Throwable $e) {
                Logger::error("Jira failed", ['request_id' => $requestId, 'error' => $e->getMessage()]);
                if ($jiraRule === 'required') {
                    $this->respond(502, [
                        'error' => 'Jira-Ticket konnte nicht erstellt werden (required)',
                        'request_id' => $requestId,
                        'mail_sent' => true,
                    ]);
                    return;
                }
                // optional: log but continue
            }
        }

        // 6. Optional NetBox sync
        $netboxSynced = false;
        $netboxError = null;
        $netboxStatus = null;
        $syncRule = $this->config->getNetBoxSyncRule($eventType);

        if ($syncRule !== 'none') {
            try {
                $netboxResult = $this->syncNetBox($eventType, $event, $data, $recipients['to'], $requestId, $syncRule);
                $netboxSynced = true;
                $netboxStatus = $netboxResult['status'] ?? null;
                Logger::info("NetBox sync completed", ['request_id' => $requestId, 'sync_rule' => $syncRule]);
            } catch (\Throwable $e) {
                $netboxError = $e->getMessage();
                Logger::error("NetBox sync failed", ['request_id' => $requestId, 'error' => $netboxError]);
                if ($this->config->getNetBoxOnError() === 'fail') {
                    $this->respond(502, [
                        'error' => 'NetBox-Update fehlgeschlagen',
                        'mail_sent' => true,
                        'request_id' => $requestId,
                    ]);
                    return;
                }
            }
        }

        // 7. Success response
        $response = [
            'success' => true,
            'request_id' => $requestId,
            'mail_sent' => true,
            'event_type' => $eventType,
            'asset_id' => $assetId,
            'jira_ticket' => $jiraTicket,
            'netbox_synced' => $netboxSynced,
        ];

        if ($netboxStatus !== null) {
            $response['netbox_status'] = $netboxStatus;
        }
        if ($netboxError !== null) {
            $response['netbox_error'] = $netboxError;
        }

        $this->respond(200, $response);
    }

    private function syncNetBox(string $eventType, array $event, array $data, string $evidenceTo, string $requestId, string $syncRule): array
    {
        $client = new NetBoxClient($this->config);
        $assetId = $data['asset_id'] ?? '';
        $result = ['status' => null];

        // Find the device in NetBox
        $device = $client->findDeviceByAssetTag($assetId, $requestId);
        if ($device === null) {
            throw new \RuntimeException('Asset nicht in NetBox gefunden');
        }

        $deviceId = (int) $device['id'];

        // Status update + custom fields (only for update_status rule)
        if ($syncRule === 'update_status') {
            $targetStatus = StatusMapper::getTargetStatus($eventType);
            $patchData = [];

            if ($targetStatus !== null) {
                $patchData['status'] = $targetStatus;
                $result['status'] = $targetStatus;
            }

            // Custom fields
            $customFields = CustomFieldMapper::map($eventType, $data);
            if (!empty($customFields)) {
                $patchData['custom_fields'] = $customFields;
            }

            if (!empty($patchData)) {
                $client->updateDevice($deviceId, $patchData, $requestId);
            }
        }

        // Journal entry (for both update_status and journal_only)
        $kind = StatusMapper::getJournalKind($eventType);
        $comments = JournalBuilder::build($eventType, $event, $data, $requestId, $evidenceTo);

        $client->createJournalEntry([
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

    private function validate(string $eventType, array $event, array $data): array
    {
        $errors = [];

        foreach ($event['required_fields'] as $field) {
            $val = $data[$field] ?? null;
            // Boolean fields (checkboxes): must be true
            if ($val === false || $val === null || $val === '') {
                $errors[$field] = 'Pflichtfeld';
            }
        }

        // Conditional: rz_retire — data_handling_ref required if data_handling != "Nicht relevant"
        if ($eventType === 'rz_retire') {
            $dh = $data['data_handling'] ?? '';
            if ($dh !== '' && $dh !== 'Nicht relevant') {
                if (empty($data['data_handling_ref'])) {
                    $errors['data_handling_ref'] = 'Pflichtfeld (Data Handling ≠ Nicht relevant)';
                }
            }
        }

        // Conditional: admin_access_cleanup — ticket_ref required if device_wiped is not checked
        if ($eventType === 'admin_access_cleanup') {
            if (empty($data['device_wiped']) || $data['device_wiped'] === false) {
                if (empty($data['ticket_ref'])) {
                    $errors['ticket_ref'] = 'Pflichtfeld (Wipe nicht abgeschlossen)';
                }
            }
        }

        return $errors;
    }

    private function respond(int $status, array $data): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
