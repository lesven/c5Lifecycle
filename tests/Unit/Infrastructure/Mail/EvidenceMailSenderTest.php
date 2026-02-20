<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Infrastructure\Config\EvidenceConfig;
use App\Infrastructure\Mail\EvidenceMailSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EvidenceMailSenderTest extends TestCase
{
    private function createConfig(): EvidenceConfig
    {
        $configPath = sys_get_temp_dir() . '/c5_test_config_' . uniqid() . '.yaml';
        file_put_contents($configPath, "
smtp:
  host: localhost
  port: 1025
  from_address: c5-evidence@company.de
  from_name: C5 Evidence Tool
evidence:
  rz_assets:
    to: rz@company.de
    cc: []
");
        $config = EvidenceConfig::fromYamlFile($configPath);
        unlink($configPath);
        return $config;
    }

    public function testSendCallsMailerWithCorrectEmail(): void
    {
        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
                return true;
            }));

        $sender = new EvidenceMailSender($mailer, $this->createConfig(), new NullLogger());

        $recipients = ['to' => 'rz-evidence@company.de', 'cc' => ['it-security@company.de']];
        $sender->send($recipients, '[C5 Evidence] Test', 'Body text', 'req-123');

        $this->assertNotNull($sentEmail);
        $this->assertEquals('[C5 Evidence] Test', $sentEmail->getSubject());
        $this->assertEquals('Body text', $sentEmail->getTextBody());
        $this->assertStringContainsString('rz-evidence@company.de', $sentEmail->getTo()[0]->getAddress());
        $this->assertCount(1, $sentEmail->getCc());
        $this->assertEquals('it-security@company.de', $sentEmail->getCc()[0]->getAddress());
    }

    public function testSendIncludesCustomHeaders(): void
    {
        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
                return true;
            }));

        $sender = new EvidenceMailSender($mailer, $this->createConfig(), new NullLogger());

        $recipients = ['to' => 'rz@company.de', 'cc' => []];
        $sender->send($recipients, 'Subject', 'Body', 'req-456');

        $this->assertEquals('req-456', $sentEmail->getHeaders()->get('X-Request-ID')->getBody());
        $this->assertEquals('true', $sentEmail->getHeaders()->get('X-C5-Evidence')->getBody());
    }

    public function testSendSetsFromAddress(): void
    {
        $sentEmail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
                return true;
            }));

        $sender = new EvidenceMailSender($mailer, $this->createConfig(), new NullLogger());

        $recipients = ['to' => 'test@company.de', 'cc' => []];
        $sender->send($recipients, 'Subject', 'Body', 'req-789');

        $from = $sentEmail->getFrom();
        $this->assertCount(1, $from);
        $this->assertEquals('c5-evidence@company.de', $from[0]->getAddress());
    }

    public function testSendWithEmptyCcArray(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $sender = new EvidenceMailSender($mailer, $this->createConfig(), new NullLogger());

        $recipients = ['to' => 'test@company.de', 'cc' => []];
        $sender->send($recipients, 'Subject', 'Body', 'req-000');
    }

    public function testSendPropagatesMailerException(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP connection failed'));

        $sender = new EvidenceMailSender($mailer, $this->createConfig(), new NullLogger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP connection failed');

        $recipients = ['to' => 'test@company.de', 'cc' => []];
        $sender->send($recipients, 'Subject', 'Body', 'req-err');
    }
}
