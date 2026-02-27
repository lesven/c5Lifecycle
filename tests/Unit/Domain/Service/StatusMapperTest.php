<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Service\StatusMapper;
use PHPUnit\Framework\TestCase;

class StatusMapperTest extends TestCase
{
    private StatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new StatusMapper();
    }

    public function testRzProvisionMapsToActive(): void
    {
        $this->assertEquals('active', $this->mapper->getTargetStatus('rz_provision'));
    }

    public function testRzRetireMapsToDecommissioning(): void
    {
        $this->assertEquals('decommissioning', $this->mapper->getTargetStatus('rz_retire'));
    }

    public function testAdminProvisionMapsToActive(): void
    {
        $this->assertEquals('active', $this->mapper->getTargetStatus('admin_provision'));
    }

    public function testAdminReturnMapsToInventory(): void
    {
        $this->assertEquals('inventory', $this->mapper->getTargetStatus('admin_return'));
    }

    public function testJournalOnlyEventsReturnNullStatus(): void
    {
        $this->assertNull($this->mapper->getTargetStatus('rz_owner_confirm'));
        $this->assertNull($this->mapper->getTargetStatus('admin_user_commitment'));
        $this->assertNull($this->mapper->getTargetStatus('admin_access_cleanup'));
    }

    public function testUnknownEventReturnsNullStatus(): void
    {
        $this->assertNull($this->mapper->getTargetStatus('unknown_event'));
    }

    public function testProvisionEventsHaveSuccessKind(): void
    {
        $this->assertEquals('success', $this->mapper->getJournalKind('rz_provision'));
        $this->assertEquals('success', $this->mapper->getJournalKind('admin_provision'));
    }

    public function testRetireEventsHaveSuccessKind(): void
    {
        $this->assertEquals('success', $this->mapper->getJournalKind('rz_retire'));
        $this->assertEquals('success', $this->mapper->getJournalKind('admin_return'));
    }

    public function testConfirmationEventsHaveInfoKind(): void
    {
        $this->assertEquals('info', $this->mapper->getJournalKind('rz_owner_confirm'));
        $this->assertEquals('info', $this->mapper->getJournalKind('admin_user_commitment'));
        $this->assertEquals('info', $this->mapper->getJournalKind('admin_access_cleanup'));
    }

    public function testUnknownEventDefaultsToInfoKind(): void
    {
        $this->assertEquals('info', $this->mapper->getJournalKind('unknown_event'));
    }
}
