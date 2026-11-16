<?php

declare(strict_types=1);

namespace App\Domain\Audit\Service;

use PDO;

final readonly class LogRotationService
{
    public const MIN_RETENTION_DAYS = 90;

    public function __construct(private PDO $pdo) {}

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
        $deleted = 0;

        $waStmt = $this->pdo->prepare('DELETE FROM wagateway_outbox WHERE created_at < :cutoff');
        $waStmt->execute([':cutoff' => $cutoff]);
        $deleted += $waStmt->rowCount();

        $brStmt = $this->pdo->prepare(
            "DELETE FROM bridging_sync_queue WHERE status IN ('Synced', 'Failed') AND created_at < :cutoff"
        );
        $brStmt->execute([':cutoff' => $cutoff]);
        $deleted += $brStmt->rowCount();

        return $deleted;
    }
}
