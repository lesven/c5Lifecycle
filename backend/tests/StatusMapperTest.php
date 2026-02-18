<?php
declare(strict_types=1);

namespace C5\Tests;

use C5\NetBox\StatusMapper;
use PHPUnit\Framework\TestCase;

class StatusMapperTest extends TestCase
{
    public function testRzProvisionMapsToActive(): void
    {
        $this->assertEquals('active', StatusMapper::getTargetStatus('rz_provision'));
    }

    public function testRzRetireMapsToDecommissioning(): void
    {
        $this->assertEquals('decommissioning', StatusMapper::getTargetStatus('rz_retire'));
    }

    public function testAdminProvisionMapsToActive(): void
    {
        $this->assertEquals('active', StatusMapper::getTargetStatus('admin_provision'));
    }

    public function testAdminReturnMapsToInventory(): void
    {
        $this->assertEquals('inventory', StatusMapper::getTargetStatus('admin_return'));
    }

    public function testJournalOnlyEventsReturnNullStatus(): void
    {
        $this->assertNull(StatusMapper::getTargetStatus('rz_owner_confirm'));
        $this->assertNull(StatusMapper::getTargetStatus('admin_user_commitment'));
        $this->assertNull(StatusMapper::getTargetStatus('admin_access_cleanup'));
    }

    public function testUnknownEventReturnsNullStatus(): void
    {
        $this->assertNull(StatusMapper::getTargetStatus('unknown_event'));
    }

    public function testProvisionEventsHaveSuccessKind(): void
    {
        $this->assertEquals('success', StatusMapper::getJournalKind('rz_provision'));
        $this->assertEquals('success', StatusMapper::getJournalKind('admin_provision'));
    }

    public function testRetireEventsHaveSuccessKind(): void
    {
        $this->assertEquals('success', StatusMapper::getJournalKind('rz_retire'));
        $this->assertEquals('success', StatusMapper::getJournalKind('admin_return'));
    }

    public function testConfirmationEventsHaveInfoKind(): void
    {
        $this->assertEquals('info', StatusMapper::getJournalKind('rz_owner_confirm'));
        $this->assertEquals('info', StatusMapper::getJournalKind('admin_user_commitment'));
        $this->assertEquals('info', StatusMapper::getJournalKind('admin_access_cleanup'));
    }

    public function testUnknownEventDefaultsToInfoKind(): void
    {
        $this->assertEquals('info', StatusMapper::getJournalKind('unknown_event'));
    }
}
