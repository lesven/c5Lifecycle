<?php

declare(strict_types=1);

namespace App\Domain\Repository;

/**
 * Sends evidence emails to the configured archive mailbox.
 *
 * Implementations handle SMTP transport, header enrichment (X-Request-ID),
 * and from/cc configuration.
 */
interface EvidenceMailSenderInterface
{
    /**
     * @param array{to: string, cc: string[]} $recipients
     */
    public function send(array $recipients, string $subject, string $body, string $requestId): void;
}
