<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Infrastructure\Config\EvidenceConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class EvidenceMailSender
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EvidenceConfig $config,
        private readonly LoggerInterface $evidenceLogger,
    ) {}

    /**
     * @param array{to: string, cc: string[]} $recipients
     */
    public function send(array $recipients, string $subject, string $body, string $requestId): void
    {
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->config->getSmtpFromName(), $this->config->getSmtpFromAddress()))
            ->to($recipients['to'])
            ->subject($subject)
            ->text($body);

        foreach ($recipients['cc'] as $cc) {
            $email->addCc($cc);
        }

        $email->getHeaders()
            ->addTextHeader('X-Request-ID', $requestId)
            ->addTextHeader('X-C5-Evidence', 'true');

        $this->evidenceLogger->info('Sending evidence email', [
            'request_id' => $requestId,
            'to' => $recipients['to'],
            'subject' => $subject,
        ]);

        $this->mailer->send($email);

        $this->evidenceLogger->info('Evidence email sent', [
            'request_id' => $requestId,
            'to' => $recipients['to'],
        ]);
    }
}
