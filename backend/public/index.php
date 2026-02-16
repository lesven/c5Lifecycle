<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use C5\Bootstrap;
use C5\Router;

// CORS headers for frontend dev
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $config = Bootstrap::init();
    $router = new Router($config);
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Interner Serverfehler',
        'detail' => $e->getMessage(),
    ]);
}
