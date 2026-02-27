<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\SubmissionLogRepositoryInterface;
use App\Infrastructure\Persistence\Entity\SubmissionLog;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSubmissionLogRepository implements SubmissionLogRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(SubmissionLog $log): void
    {
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function findByRequestId(string $requestId): ?SubmissionLog
    {
        return $this->entityManager->getRepository(SubmissionLog::class)
            ->findOneBy(['requestId' => $requestId]);
    }
}
