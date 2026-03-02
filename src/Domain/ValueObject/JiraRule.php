<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Defines when a Jira ticket must be created for a submission event.
 */
enum JiraRule: string
{
    /** No Jira ticket. */
    case None = 'none';

    /** Jira ticket is created if possible, but failure is non-blocking. */
    case Optional = 'optional';

    /** Jira ticket creation is mandatory; failure aborts the submission. */
    case Required = 'required';
}
