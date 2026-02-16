<?php
declare(strict_types=1);

namespace C5\Jira;

use C5\Config;

class JiraClient
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Create a Jira ticket and return the ticket key (e.g. "ITOPS-123")
     */
    public function createTicket(array $event, array $data, string $requestId): string
    {
        $baseUrl    = $this->config->get('jira.base_url');
        $projectKey = $this->config->get('jira.project_key', 'ITOPS');
        $issueType  = $this->config->get('jira.issue_type', 'Task');
        $token      = $this->config->get('jira.api_token', '');

        $assetId = $data['asset_id'] ?? 'UNKNOWN';
        $summary = sprintf('[C5] %s – %s', $event['label'], $assetId);

        // Build description from form data
        $descLines = [
            "C5 Evidence – {$event['label']}",
            "Request-ID: {$requestId}",
            "",
        ];
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'Ja' : 'Nein';
            }
            $descLines[] = "*{$key}*: {$value}";
        }
        $description = implode("\n", $descLines);

        $payload = [
            'fields' => [
                'project'     => ['key' => $projectKey],
                'summary'     => $summary,
                'description' => $description,
                'issuetype'   => ['name' => $issueType],
            ],
        ];

        $url = rtrim($baseUrl, '/') . '/rest/api/2/issue';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Jira cURL error: ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("Jira API error (HTTP {$httpCode}): " . ($response ?: 'no response'));
        }

        $result = json_decode($response, true);
        if (!isset($result['key'])) {
            throw new \RuntimeException('Jira response missing ticket key');
        }

        return $result['key'];
    }
}
