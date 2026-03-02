<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Defines what NetBox data is updated when an event is submitted.
 */
enum NetBoxSyncRule: string
{
    /** No NetBox interaction. */
    case None = 'none';

    /** Update device status and custom fields. */
    case UpdateStatus = 'update_status';

    /** Create a journal entry only; do not change device status. */
    case JournalOnly = 'journal_only';
}
