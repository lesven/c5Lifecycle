<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\AssetId;
use PHPUnit\Framework\TestCase;

final class AssetIdTest extends TestCase
{
    public function testFromSetsValue(): void
    {
        $id = AssetId::from('SRV-001');
        $this->assertSame('SRV-001', $id->value);
    }

    public function testFromNullFallsBackToUnknown(): void
    {
        $id = AssetId::from(null);
        $this->assertSame(AssetId::UNKNOWN, $id->value);
        $this->assertTrue($id->isUnknown());
    }

    public function testFromEmptyStringFallsBackToUnknown(): void
    {
        $id = AssetId::from('');
        $this->assertSame(AssetId::UNKNOWN, $id->value);
        $this->assertTrue($id->isUnknown());
    }

    public function testIsUnknownReturnsFalseForRealId(): void
    {
        $id = AssetId::from('WS-0042');
        $this->assertFalse($id->isUnknown());
    }

    public function testToStringReturnsValue(): void
    {
        $id = AssetId::from('SRV-12345');
        $this->assertSame('SRV-12345', (string) $id);
    }

    public function testStringableInInterpolation(): void
    {
        $id = AssetId::from('NET-007');
        $this->assertSame('Asset: NET-007', "Asset: {$id}");
    }
}
