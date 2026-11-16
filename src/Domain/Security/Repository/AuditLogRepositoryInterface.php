<?php

declare(strict_types=1);

namespace App\Domain\Security\Repository;

interface AuditLogRepositoryInterface
{
    public function log(int $userId, string $username, string $action, string $details, string $ipAddress): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 50, ?string $action = null): array;
}
