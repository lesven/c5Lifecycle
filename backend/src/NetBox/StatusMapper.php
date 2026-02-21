<?php
declare(strict_types=1);

namespace C5\NetBox;

class StatusMapper
{
    /**
     * Event type → NetBox device status mapping.
     * Only events with sync_rules = "update_status" get a status update.
     */
    private static array $statusMap = [
        'rz_provision'    => 'active',
        'rz_retire'       => 'decommissioning',
        'admin_provision'  => 'active',
        'admin_return'     => 'inventory',
    ];

    /**
     * Journal entry kind per event type.
     */
    private static array $kindMap = [
        'rz_provision'          => 'success',
        'rz_retire'             => 'success',
        'rz_owner_confirm'      => 'info',
        'admin_provision'       => 'success',
        'admin_user_commitment' => 'info',
        'admin_return'          => 'success',
        'admin_access_cleanup'  => 'info',
    ];

    /**
     * Get the target NetBox status for a given event type, or null if no status change.
     */
    public static function getTargetStatus(string $eventType): ?string
    {
        return self::$statusMap[$eventType] ?? null;
    }

    /**
     * Get the journal entry kind for a given event type.
     */
    public static function getJournalKind(string $eventType): string
    {
        return self::$kindMap[$eventType] ?? 'info';
    }
}
