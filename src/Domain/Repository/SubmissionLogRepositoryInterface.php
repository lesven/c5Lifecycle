<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Infrastructure\Persistence\Entity\SubmissionLog;

interface SubmissionLogRepositoryInterface
{
    public function save(SubmissionLog $log): void;

    public function findByRequestId(string $requestId): ?SubmissionLog;
}
