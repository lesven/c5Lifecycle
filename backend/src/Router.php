<?php
declare(strict_types=1);

namespace C5;

use C5\Handler\SubmitHandler;
use C5\Handler\AssetLookupHandler;
use C5\Handler\TenantsHandler;
use C5\Handler\ContactsHandler;

class Router
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function dispatch(string $method, string $uri): void
    {
        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH);

        // Match: POST /api/submit/{event-type}
        if ($method === 'POST' && preg_match('#^/api/submit/([\w_-]+)$#', $path, $m)) {
            $eventType = str_replace('-', '_', $m[1]);
            $handler = new SubmitHandler($this->config);
            $handler->handle($eventType);
            return;
        }

        // Health check
        if ($method === 'GET' && ($path === '/api/health' || $path === '/health')) {
            echo json_encode(['status' => 'ok']);
            return;
        }

        // Asset lookup: GET /api/asset-lookup?asset_id={id}
        if ($method === 'GET' && $path === '/api/asset-lookup') {
            $handler = new AssetLookupHandler($this->config);
            $handler->handle();
            return;
        }

        // Tenants list: GET /api/tenants
        if ($method === 'GET' && $path === '/api/tenants') {
            $handler = new TenantsHandler($this->config);
            $handler->handle();
            return;
        }

        // Contacts list: GET /api/contacts
        if ($method === 'GET' && $path === '/api/contacts') {
            $handler = new ContactsHandler($this->config);
            $handler->handle();
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Route nicht gefunden: ' . $method . ' ' . $path]);
    }
}
