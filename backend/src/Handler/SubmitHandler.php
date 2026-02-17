<?php
declare(strict_types=1);

namespace C5\Handler;

use C5\Config;
use C5\EventRegistry;
use C5\Mail\MailBuilder;
use C5\Mail\MailSender;
use C5\Jira\JiraClient;
use C5\Log\Logger;
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

        // 6. Success response
        $this->respond(200, [
            'success' => true,
            'request_id' => $requestId,
            'mail_sent' => true,
            'event_type' => $eventType,
            'asset_id' => $assetId,
            'jira_ticket' => $jiraTicket,
        ]);
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
