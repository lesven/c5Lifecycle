<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\EventDefinition;
use PHPUnit\Framework\TestCase;

class EventDefinitionTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        $definition = new EventDefinition(
            track: 'rz_assets',
            label: 'Inbetriebnahme RZ-Asset',
            category: 'RZ',
            subjectType: 'Inbetriebnahme',
            requiredFields: ['asset_id', 'device_type'],
        );

        $this->assertSame('rz_assets', $definition->track);
        $this->assertSame('Inbetriebnahme RZ-Asset', $definition->label);
        $this->assertSame('RZ', $definition->category);
        $this->assertSame('Inbetriebnahme', $definition->subjectType);
        $this->assertSame(['asset_id', 'device_type'], $definition->requiredFields);
    }

    public function testReadonlyProperties(): void
    {
        $definition = new EventDefinition(
            track: 'admin_devices',
            label: 'Rückgabe Admin-Endgerät',
            category: 'ADM',
            subjectType: 'Rückgabe',
            requiredFields: ['asset_id', 'return_date'],
        );

        // Value objects are readonly — properties cannot be changed after construction.
        $reflection = new \ReflectionClass($definition);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testEmptyRequiredFields(): void
    {
        $definition = new EventDefinition(
            track: 'test',
            label: 'Test',
            category: 'TST',
            subjectType: 'Test',
            requiredFields: [],
        );

        $this->assertSame([], $definition->requiredFields);
    }

    public function testUmlautsInLabel(): void
    {
        $definition = new EventDefinition(
            track: 'rz_assets',
            label: 'Außerbetriebnahme RZ-Asset',
            category: 'RZ',
            subjectType: 'Außerbetriebnahme',
            requiredFields: [],
        );

        $this->assertStringContainsString('ß', $definition->label);
        $this->assertStringContainsString('ß', $definition->subjectType);
    }

    public function testConditionalRulesDefaultEmpty(): void
    {
        $definition = new EventDefinition(
            track: 'test',
            label: 'Test',
            category: 'TST',
            subjectType: 'Test',
            requiredFields: [],
        );

        $this->assertSame([], $definition->conditionalRules);
    }

    public function testConditionalRulesAssigned(): void
    {
        $rules = [
            'data_handling_ref' => [
                'when' => ['field' => 'data_handling', 'operator' => 'not_in', 'value' => ['', 'Nicht relevant']],
                'then' => 'Pflichtfeld (Data Handling ≠ Nicht relevant)',
            ],
        ];

        $definition = new EventDefinition(
            track: 'rz_assets',
            label: 'Außerbetriebnahme RZ-Asset',
            category: 'RZ',
            subjectType: 'Außerbetriebnahme',
            requiredFields: ['asset_id'],
            conditionalRules: $rules,
        );

        $this->assertSame($rules, $definition->conditionalRules);
        $this->assertArrayHasKey('data_handling_ref', $definition->conditionalRules);
    }
}
