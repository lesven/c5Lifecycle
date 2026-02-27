<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Infrastructure\Persistence\Entity\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /** @return User[] */
    public function findAll(): array;
}
