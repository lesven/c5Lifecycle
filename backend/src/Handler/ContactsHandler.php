<?php
declare(strict_types=1);

namespace C5\Handler;

use C5\Config;
use C5\Log\Logger;
use C5\NetBox\NetBoxClient;
use Ramsey\Uuid\Uuid;

class ContactsHandler
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        $requestId = Uuid::uuid4()->toString();

        if (!$this->config->get('netbox.enabled', false)) {
            http_response_code(200);
            echo json_encode([]);
            return;
        }

        try {
            $client = new NetBoxClient($this->config);
            $contacts = $client->getContacts($requestId);
            $result = array_map(function (array $c) {
                return ['id' => $c['id'], 'name' => $c['name']];
            }, $contacts);
            http_response_code(200);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Logger::error("Contacts fetch failed", [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            http_response_code(200);
            echo json_encode([]);
        }
    }
}
