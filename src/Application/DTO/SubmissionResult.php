<?php

declare(strict_types=1);

namespace App\Application\DTO;

final class SubmissionResult
{
    public bool $success = false;
    public string $requestId;
    public string $eventType;
    public string $assetId;
    public bool $mailSent = false;
    public ?string $jiraTicket = null;
    public bool $netboxSynced = false;
    public ?string $netboxStatus = null;
    public ?string $netboxError = null;
    public ?string $netboxErrorTrace = null;
    public ?string $error = null;
    public ?array $validationErrors = null;
    public int $httpStatus = 200;
}
