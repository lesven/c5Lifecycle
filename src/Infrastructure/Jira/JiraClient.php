<?php

declare(strict_types=1);

namespace App\Infrastructure\Jira;

use App\Domain\Repository\JiraClientInterface;
use App\Domain\ValueObject\EventDefinition;
use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\Http\ApiResponseParser;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JiraClient implements JiraClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly EvidenceConfig $config,
        private readonly LoggerInterface $jiraLogger,
    ) {
    }

    public function createTicket(EventDefinition $event, array $data, string $requestId): string
    {
        $projectKey = $this->config->getJiraProjectKey();
        $issueType = $this->config->getJiraIssueType();

        $assetId = $data['asset_id'] ?? 'UNKNOWN';
        $summary = sprintf('[C5] %s – %s', $event->label, $assetId);

        $descLines = [
            "C5 Evidence – {$event->label}",
            "Request-ID: {$requestId}",
            '',
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
                'project' => ['key' => $projectKey],
                'summary' => $summary,
                'description' => $description,
                'issuetype' => ['name' => $issueType],
            ],
        ];

        $this->jiraLogger->info('Creating Jira ticket', ['request_id' => $requestId, 'summary' => $summary]);

        $response = $this->jiraClient->request('POST', '/rest/api/2/issue', [
            'json' => $payload,
        ]);

        $result = ApiResponseParser::parse($response, 'Jira', $requestId, $this->jiraLogger);
        if ($result === null || !isset($result['key'])) {
            throw new RuntimeException('Jira response missing ticket key');
        }

        $this->jiraLogger->info('Jira ticket created', ['request_id' => $requestId, 'ticket' => $result['key']]);

        return $result['key'];
    }
}
