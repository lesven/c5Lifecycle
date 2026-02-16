<?php
declare(strict_types=1);

namespace C5;

use C5\Log\Logger;

class Bootstrap
{
    public static function init(): Config
    {
        $configPath = __DIR__ . '/../config/config.yaml';

        if (!file_exists($configPath)) {
            Logger::error('Config file not found: ' . $configPath);
            http_response_code(500);
            echo json_encode(['error' => 'Konfiguration nicht gefunden. Bitte config.yaml anlegen.']);
            exit(1);
        }

        $config = Config::load($configPath);

        // Validate required config sections
        $required = ['smtp', 'evidence'];
        foreach ($required as $section) {
            if (!$config->has($section)) {
                Logger::error('Missing required config section: ' . $section);
                http_response_code(500);
                echo json_encode(['error' => "Config-Sektion '$section' fehlt."]);
                exit(1);
            }
        }

        return $config;
    }
}
