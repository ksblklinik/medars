<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repository;

interface LogRotationRepositoryInterface
{
    public function pruneOperationalLogsBefore(string $cutoffDateTime): int;
}
