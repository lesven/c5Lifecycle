<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\FormData;
use PHPUnit\Framework\TestCase;

class FormDataTest extends TestCase
{
    public function testGetStringReturnsValue(): void
    {
        $formData = new FormData(['asset_id' => 'SRV-001', 'location' => 'DC-1']);

        $this->assertSame('SRV-001', $formData->getString('asset_id'));
        $this->assertSame('DC-1', $formData->getString('location'));
    }

    public function testGetStringReturnsDefaultForMissingKey(): void
    {
        $formData = new FormData(['asset_id' => 'SRV-001']);

        $this->assertSame('', $formData->getString('missing'));
        $this->assertSame('default', $formData->getString('missing', 'default'));
    }

    public function testGetStringReturnsDefaultForNonStringValue(): void
    {
        $formData = new FormData(['count' => 42, 'flag' => true]);

        $this->assertSame('', $formData->getString('count'));
        $this->assertSame('', $formData->getString('flag'));
    }

    public function testGetBoolReturnsTrueForTruthyValues(): void
    {
        $formData = new FormData([
            'checked' => true,
            'filled' => 'yes',
            'number' => 1,
        ]);

        $this->assertTrue($formData->getBool('checked'));
        $this->assertTrue($formData->getBool('filled'));
        $this->assertTrue($formData->getBool('number'));
    }

    public function testGetBoolReturnsFalseForFalsyValues(): void
    {
        $formData = new FormData([
            'unchecked' => false,
            'empty' => '',
            'zero' => 0,
            'null_val' => null,
        ]);

        $this->assertFalse($formData->getBool('unchecked'));
        $this->assertFalse($formData->getBool('empty'));
        $this->assertFalse($formData->getBool('zero'));
        $this->assertFalse($formData->getBool('null_val'));
        $this->assertFalse($formData->getBool('nonexistent'));
    }

    public function testHasReturnsTrueForNonEmptyValues(): void
    {
        $formData = new FormData([
            'asset_id' => 'SRV-001',
            'flag' => true,
        ]);

        $this->assertTrue($formData->has('asset_id'));
        $this->assertTrue($formData->has('flag'));
    }

    public function testHasReturnsFalseForEmptyAndMissingValues(): void
    {
        $formData = new FormData([
            'empty' => '',
            'null_val' => null,
        ]);

        $this->assertFalse($formData->has('empty'));
        $this->assertFalse($formData->has('null_val'));
        $this->assertFalse($formData->has('nonexistent'));
    }

    public function testToArrayReturnsOriginalData(): void
    {
        $data = ['asset_id' => 'SRV-001', 'flag' => true, 'count' => 42];
        $formData = new FormData($data);

        $this->assertSame($data, $formData->toArray());
    }

    public function testEmptyFormData(): void
    {
        $formData = new FormData([]);

        $this->assertSame([], $formData->toArray());
        $this->assertSame('', $formData->getString('any'));
        $this->assertFalse($formData->getBool('any'));
        $this->assertFalse($formData->has('any'));
    }
}
