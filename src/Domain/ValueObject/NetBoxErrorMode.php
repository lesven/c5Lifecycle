<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Defines how errors during a NetBox sync are handled.
 */
enum NetBoxErrorMode: string
{
    /** Log the error and continue — submission still succeeds. */
    case Warn = 'warn';

    /** Re-throw the error — submission fails. */
    case Fail = 'fail';
}
