<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\LogContext;
use PHPUnit\Framework\TestCase;

final class LogContextTest extends TestCase
{
    public function testForSetsRequestId(): void
    {
        $ctx = LogContext::for('req-1')->toArray();
        $this->assertSame('req-1', $ctx['request_id']);
    }

    public function testWithEventAddsKey(): void
    {
        $ctx = LogContext::for('req-1')->withEvent('rz_provision')->toArray();
        $this->assertSame('rz_provision', $ctx['event']);
    }

    public function testWithErrorAddsExceptionAndErrorKeys(): void
    {
        $e = new \RuntimeException('boom');
        $ctx = LogContext::for('req-1')->withError($e)->toArray();
        $this->assertSame(\RuntimeException::class, $ctx['exception']);
        $this->assertSame('boom', $ctx['error']);
    }

    public function testWithAddsArbitraryKey(): void
    {
        $ctx = LogContext::for('req-1')->with('foo', 'bar')->toArray();
        $this->assertSame('bar', $ctx['foo']);
    }

    public function testWithAssetAcceptsAssetId(): void
    {
        $assetId = AssetId::from('SRV-001');
        $ctx = LogContext::for('req-1')->withAsset($assetId)->toArray();
        $this->assertSame('SRV-001', $ctx['asset_id']);
    }

    public function testWithAssetAcceptsString(): void
    {
        $ctx = LogContext::for('req-1')->withAsset('WS-042')->toArray();
        $this->assertSame('WS-042', $ctx['asset_id']);
    }

    public function testIsImmutable(): void
    {
        $base = LogContext::for('req-1');
        $extended = $base->withEvent('rz_retire');
        $this->assertArrayNotHasKey('event', $base->toArray());
        $this->assertArrayHasKey('event', $extended->toArray());
    }
}
