<?php

declare(strict_types=1);

namespace App\Domain\Security\Repository;

use App\Domain\Security\Entity\UserAccount;

interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?UserAccount;

    public function findById(int $id): ?UserAccount;

    /**
     * @return list<UserAccount>
     */
    public function findAll(): array;

    public function save(UserAccount $user): void;

    public function recordLoginSuccess(int $userId, string $ipAddress): void;

    public function recordLoginFailure(string $username, string $ipAddress): void;

    public function isRateLimited(string $username, string $ipAddress, int $maxAttempts = 5, int $decaySeconds = 300): bool;
}
