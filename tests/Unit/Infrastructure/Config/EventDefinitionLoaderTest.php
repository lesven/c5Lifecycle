<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Config;

use App\Infrastructure\Config\EventDefinitionLoader;
use PHPUnit\Framework\TestCase;

class EventDefinitionLoaderTest extends TestCase
{
    public function testLoadFromProjectYaml(): void
    {
        $path = __DIR__ . '/../../../../config/event_definitions.yaml';
        $definitions = EventDefinitionLoader::load($path);

        $this->assertCount(7, $definitions);
        $this->assertArrayHasKey('rz_provision', $definitions);
        $this->assertArrayHasKey('rz_retire', $definitions);
        $this->assertArrayHasKey('rz_owner_confirm', $definitions);
        $this->assertArrayHasKey('admin_provision', $definitions);
        $this->assertArrayHasKey('admin_user_commitment', $definitions);
        $this->assertArrayHasKey('admin_return', $definitions);
        $this->assertArrayHasKey('admin_access_cleanup', $definitions);
    }

    public function testRzProvisionDefinition(): void
    {
        $path = __DIR__ . '/../../../../config/event_definitions.yaml';
        $definitions = EventDefinitionLoader::load($path);

        $rz = $definitions['rz_provision'];
        $this->assertSame('rz_assets', $rz->track);
        $this->assertSame('Inbetriebnahme RZ-Asset', $rz->label);
        $this->assertSame('RZ', $rz->category);
        $this->assertSame('Inbetriebnahme', $rz->subjectType);
        $this->assertContains('asset_id', $rz->requiredFields);
        $this->assertContains('device_type', $rz->requiredFields);
        $this->assertContains('monitoring_active', $rz->requiredFields);
        $this->assertSame([], $rz->conditionalRules);
    }

    public function testRzRetireHasConditionalRules(): void
    {
        $path = __DIR__ . '/../../../../config/event_definitions.yaml';
        $definitions = EventDefinitionLoader::load($path);

        $retire = $definitions['rz_retire'];
        $this->assertArrayHasKey('data_handling_ref', $retire->conditionalRules);
        $rule = $retire->conditionalRules['data_handling_ref'];
        $this->assertSame('data_handling', $rule['when']['field']);
        $this->assertSame('not_in', $rule['when']['operator']);
        $this->assertContains('Nicht relevant', $rule['when']['value']);
    }

    public function testAdminAccessCleanupHasConditionalRules(): void
    {
        $path = __DIR__ . '/../../../../config/event_definitions.yaml';
        $definitions = EventDefinitionLoader::load($path);

        $cleanup = $definitions['admin_access_cleanup'];
        $this->assertArrayHasKey('ticket_ref', $cleanup->conditionalRules);
        $rule = $cleanup->conditionalRules['ticket_ref'];
        $this->assertSame('device_wiped', $rule['when']['field']);
        $this->assertSame('empty', $rule['when']['operator']);
    }

    public function testMissingFileThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');
        EventDefinitionLoader::load('/nonexistent/path.yaml');
    }
}
