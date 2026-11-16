<?php

declare(strict_types=1);

namespace App\Domain\Audit\Service;

use App\Domain\Audit\Repository\LogRotationRepositoryInterface;

final readonly class LogRotationService
{
    public const MIN_RETENTION_DAYS = 90;

    public function __construct(private LogRotationRepositoryInterface $repo) {}

    /**
     * Rotates operational communication/sync logs older than retention days.
     * Audit trail (mlite_logs) is immutable and NEVER pruned.
     */
    public function pruneLogs(int $olderThanDays = self::MIN_RETENTION_DAYS): int
    {
        if ($olderThanDays < self::MIN_RETENTION_DAYS) {
            throw new \InvalidArgumentException(
                'Retensi minimum rotasi log adalah ' . self::MIN_RETENTION_DAYS . ' hari.'
            );
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));
        return $this->repo->pruneOperationalLogsBefore($cutoff);
    }
}
