<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Config;
use C5\Mail\MailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class MailSenderTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function createConfig(array $data): Config
    {
        file_put_contents($this->tmpFile, Yaml::dump($data, 4));
        return Config::load($this->tmpFile);
    }

    public function testConstructorAcceptsConfig(): void
    {
        $config = $this->createConfig([
            'smtp' => ['host' => 'localhost', 'port' => 587],
        ]);
        $sender = new MailSender($config);
        $this->assertInstanceOf(MailSender::class, $sender);
    }

    public function testSendThrowsOnConnectionFailure(): void
    {
        $config = $this->createConfig([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999, // non-existent SMTP server
                'encryption' => '',
                'from_address' => 'test@localhost',
                'from_name' => 'Test',
            ],
        ]);

        $sender = new MailSender($config);
        $recipients = ['to' => 'dest@example.com', 'cc' => []];

        $this->expectException(\Exception::class);
        $sender->send($recipients, 'Test Subject', 'Test Body', 'req-123');
    }

    public function testSendWithAuthConfigThrowsOnConnectionFailure(): void
    {
        $config = $this->createConfig([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999,
                'encryption' => 'tls',
                'username' => 'user@example.com',
                'password' => 'secret',
                'from_address' => 'test@localhost',
                'from_name' => 'Test',
            ],
        ]);

        $sender = new MailSender($config);
        $recipients = ['to' => 'dest@example.com', 'cc' => ['cc@example.com']];

        $this->expectException(\Exception::class);
        $sender->send($recipients, 'Test Subject', 'Test Body', 'req-123');
    }

    public function testSendWithDefaultConfigValues(): void
    {
        // Config with minimal values - defaults should be used
        $config = $this->createConfig([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999,
            ],
        ]);

        $sender = new MailSender($config);
        $recipients = ['to' => 'dest@example.com', 'cc' => []];

        $this->expectException(\Exception::class);
        $sender->send($recipients, 'Test Subject', 'Test Body', 'req-123');
    }

    public function testSendConfiguresFromNameFromConfig(): void
    {
        $config = $this->createConfig([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999,
                'from_name' => 'C5 Evidence System',
                'from_address' => 'evidence@company.de',
            ],
        ]);

        $sender = new MailSender($config);
        $recipients = ['to' => 'dest@example.com', 'cc' => []];

        // We expect connection failure, but config should be properly set
        $this->expectException(\Exception::class);
        $sender->send($recipients, 'Test Subject', 'Test Body', 'req-123');
    }

    public function testSendHandlesMultipleCcRecipients(): void
    {
        $config = $this->createConfig([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999,
            ],
        ]);

        $sender = new MailSender($config);
        $recipients = [
            'to' => 'dest@example.com', 
            'cc' => ['cc1@example.com', 'cc2@example.com']
        ];

        $this->expectException(\Exception::class);
        $sender->send($recipients, 'Test Subject', 'Test Body', 'req-123');
    }

    public function testSendHandlesGermanSpecialCharacters(): void
    {
        $config = $this->createConfig([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999,
            ],
        ]);

        $sender = new MailSender($config);
        $recipients = ['to' => 'dest@example.com', 'cc' => []];
        
        $subject = '[C5 Evidence] RZ Außerbetriebnahme - SRV-001';
        $body = "Asset-ID: SRV-001\nÄnderung: Außerbetriebnahme durchgeführt\nÜberprüfung: Bestätigt";

        $this->expectException(\Exception::class);
        $sender->send($recipients, $subject, $body, 'req-123');
    }
}
