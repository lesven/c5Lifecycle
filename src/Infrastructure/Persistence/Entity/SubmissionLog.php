<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'submission_log')]
#[ORM\Index(columns: ['event_type'], name: 'idx_event_type')]
#[ORM\Index(columns: ['asset_id'], name: 'idx_asset_id')]
#[ORM\Index(columns: ['submitted_at'], name: 'idx_submitted_at')]
class SubmissionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 36, unique: true)]
    private string $requestId;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $eventType;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $assetId;

    #[ORM\Column(type: Types::JSON)]
    private array $data;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $mailSent = false;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $jiraTicket = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $netboxSynced = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $submittedAt;

    public function __construct(
        string $requestId,
        string $eventType,
        string $assetId,
        array $data,
    ) {
        $this->requestId = $requestId;
        $this->eventType = $eventType;
        $this->assetId = $assetId;
        $this->data = $data;
        $this->submittedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function isMailSent(): bool
    {
        return $this->mailSent;
    }

    public function setMailSent(bool $mailSent): void
    {
        $this->mailSent = $mailSent;
    }

    public function getJiraTicket(): ?string
    {
        return $this->jiraTicket;
    }

    public function setJiraTicket(?string $jiraTicket): void
    {
        $this->jiraTicket = $jiraTicket;
    }

    public function isNetboxSynced(): bool
    {
        return $this->netboxSynced;
    }

    public function setNetboxSynced(bool $netboxSynced): void
    {
        $this->netboxSynced = $netboxSynced;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }
}
