<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\Config;
use C5\Router;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class RouterTest extends TestCase
{
    private Config $config;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'c5test_') . '.yaml';
        file_put_contents($this->tmpFile, Yaml::dump([
            'smtp' => ['host' => 'localhost'],
            'evidence' => [
                'rz_assets' => ['to' => 'test@example.com', 'cc' => []],
            ],
        ], 4));
        $this->config = Config::load($this->tmpFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testHealthCheckRoute(): void
    {
        $router = new Router($this->config);
        ob_start();
        $router->dispatch('GET', '/api/health');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals(['status' => 'ok'], $data);
    }

    public function testHealthCheckShortRoute(): void
    {
        $router = new Router($this->config);
        ob_start();
        $router->dispatch('GET', '/health');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals(['status' => 'ok'], $data);
    }

    public function testNotFoundRoute(): void
    {
        $router = new Router($this->config);
        ob_start();
        $router->dispatch('GET', '/nonexistent');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Route nicht gefunden', $data['error']);
    }

    public function testNotFoundForWrongMethod(): void
    {
        $router = new Router($this->config);
        ob_start();
        $router->dispatch('DELETE', '/api/health');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Route nicht gefunden', $data['error']);
    }

    public function testPostSubmitRouteWithUnknownEvent(): void
    {
        $router = new Router($this->config);
        ob_start();
        $router->dispatch('POST', '/api/submit/unknown_event');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Unbekannter Event-Typ', $data['error']);
    }

    public function testPostSubmitRouteConvertsDashToUnderscore(): void
    {
        $router = new Router($this->config);
        ob_start();
        $router->dispatch('POST', '/api/submit/rz-provision');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        // Since php://input is empty in test, it should return 400 (invalid JSON)
        // But it should NOT return 404 (unknown event type), proving dash-to-underscore conversion works
        $this->assertArrayHasKey('error', $data);
        $this->assertStringNotContainsString('Unbekannter Event-Typ', $data['error']);
    }

    public function testHealthCheckWithQueryString(): void
    {
        $router = new Router($this->config);
        ob_start();
        $router->dispatch('GET', '/api/health?foo=bar');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals(['status' => 'ok'], $data);
    }

    public function testGetSubmitRouteReturns404(): void
    {
        $router = new Router($this->config);
        ob_start();
        $router->dispatch('GET', '/api/submit/rz_provision');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Route nicht gefunden', $data['error']);
    }
}
