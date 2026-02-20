<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class StatusMapper
{
    private const STATUS_MAP = [
        'rz_provision' => 'active',
        'rz_retire' => 'decommissioning',
        'admin_provision' => 'active',
        'admin_return' => 'inventory',
    ];

    private const KIND_MAP = [
        'rz_provision' => 'success',
        'rz_retire' => 'success',
        'rz_owner_confirm' => 'info',
        'admin_provision' => 'success',
        'admin_user_commitment' => 'info',
        'admin_return' => 'success',
        'admin_access_cleanup' => 'info',
    ];

    public function getTargetStatus(string $eventType): ?string
    {
        return self::STATUS_MAP[$eventType] ?? null;
    }

    public function getJournalKind(string $eventType): string
    {
        return self::KIND_MAP[$eventType] ?? 'info';
    }
}
