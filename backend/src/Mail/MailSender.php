<?php
declare(strict_types=1);

namespace C5\Mail;

use C5\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailSender
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param array{to: string, cc: string[]} $recipients
     */
    public function send(array $recipients, string $subject, string $body, string $requestId): void
    {
        $mail = new PHPMailer(true);

        // SMTP config
        $mail->isSMTP();
        $mail->Host       = $this->config->get('smtp.host', 'localhost');
        $mail->Port       = (int) $this->config->get('smtp.port', 587);
        $mail->CharSet    = 'UTF-8';

        $encryption = $this->config->get('smtp.encryption', 'tls');
        if ($encryption === 'none' || $encryption === '') {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = $encryption;
        }

        $username = $this->config->get('smtp.username');
        if ($username) {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $this->config->get('smtp.password', '');
        }

        // Sender
        $fromAddr = $this->config->get('smtp.from_address', 'c5-evidence@localhost');
        $fromName = $this->config->get('smtp.from_name', 'C5 Evidence Tool');
        $mail->setFrom($fromAddr, $fromName);

        // Recipients
        $mail->addAddress($recipients['to']);
        foreach ($recipients['cc'] ?? [] as $cc) {
            $mail->addCC($cc);
        }

        // Headers
        $mail->addCustomHeader('X-Request-ID', $requestId);
        $mail->addCustomHeader('X-C5-Evidence', 'true');

        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    }
}
