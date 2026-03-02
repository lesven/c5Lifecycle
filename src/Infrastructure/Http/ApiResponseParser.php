<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Parses HTTP API responses and throws a RuntimeException for non-2xx status codes.
 *
 * Centralises the identical response-handling pattern shared by NetBoxClient and JiraClient.
 */
final class ApiResponseParser
{
    /**
     * Assert 2xx, log error and throw on non-2xx, then decode JSON body.
     *
     * @throws RuntimeException on non-2xx HTTP status
     */
    public static function parse(
        ResponseInterface $response,
        string $apiName,
        string $requestId,
        LoggerInterface $logger,
    ): ?array {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = $response->getContent(false);
            $logger->error("{$apiName} API error", [
                'request_id' => $requestId,
                'http_code' => $statusCode,
                'response' => $body,
            ]);
            throw new RuntimeException(
                "{$apiName} API error (HTTP {$statusCode}): " . ($body ?: 'no response')
            );
        }

        $content = $response->getContent();
        $result = json_decode($content, true);

        return is_array($result) ? $result : null;
    }
}
