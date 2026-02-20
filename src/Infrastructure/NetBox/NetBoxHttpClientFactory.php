<?php

declare(strict_types=1);

namespace App\Infrastructure\NetBox;

use App\Infrastructure\Config\EvidenceConfig;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NetBoxHttpClientFactory
{
    public static function create(EvidenceConfig $config): HttpClientInterface
    {
        $baseUrl = rtrim((string) $config->get('netbox.base_url', ''), '/');
        $token = (string) $config->get('netbox.api_token', '');
        $timeout = (float) $config->get('netbox.timeout', 10);
        $verifySsl = (bool) $config->get('netbox.verify_ssl', true);

        return HttpClient::create([
            'base_uri' => $baseUrl,
            'headers' => [
                'Authorization' => 'Token ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => $timeout,
            'verify_peer' => $verifySsl,
            'verify_host' => $verifySsl,
        ]);
    }
}
