<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum Track: string
{
    case RzAssets = 'rz_assets';
    case AdminDevices = 'admin_devices';
}
