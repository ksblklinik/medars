<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Audit\Repository\LogRotationRepositoryInterface;
use PDO;

final readonly class PdoLogRotationRepository implements LogRotationRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function pruneOperationalLogsBefore(string $cutoffDateTime): int
    {
        $deleted = 0;

        $waStmt = $this->pdo->prepare('DELETE FROM wagateway_outbox WHERE created_at < :cutoff');
        $waStmt->execute([':cutoff' => $cutoffDateTime]);
        $deleted += $waStmt->rowCount();

        $brStmt = $this->pdo->prepare(
            "DELETE FROM bridging_sync_queue WHERE status IN ('Synced', 'Failed') AND created_at < :cutoff"
        );
        $brStmt->execute([':cutoff' => $cutoffDateTime]);
        $deleted += $brStmt->rowCount();

        return $deleted;
    }
}
