<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum EventType: string
{
    case RzProvision = 'rz_provision';

    /**
     * Normalize a URL slug (with dashes) to the canonical underscore form.
     * Example: 'rz-provision' → 'rz_provision'
     */
    public static function normalize(string $slug): string
    {
        return str_replace('-', '_', $slug);
    }
    case RzRetire = 'rz_retire';
    case RzOwnerConfirm = 'rz_owner_confirm';
    case AdminProvision = 'admin_provision';
    case AdminUserCommitment = 'admin_user_commitment';
    case AdminReturn = 'admin_return';
    case AdminAccessCleanup = 'admin_access_cleanup';

    public function track(): Track
    {
        return match ($this) {
            self::RzProvision, self::RzRetire, self::RzOwnerConfirm => Track::RzAssets,
            self::AdminProvision, self::AdminUserCommitment, self::AdminReturn, self::AdminAccessCleanup => Track::AdminDevices,
        };
    }

    public function isProvisionEvent(): bool
    {
        return $this === self::RzProvision || $this === self::AdminProvision;
    }
}
