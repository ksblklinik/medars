<?php

declare(strict_types=1);

namespace App\Domain\Audit\Service;

use PDO;

final readonly class LogRotationService
{
    public function __construct(private PDO $pdo) {}

    public function pruneLogs(int $olderThanDays = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$olderThanDays} days"));

        $stmt = $this->pdo->prepare('DELETE FROM mlite_logs WHERE created_at < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);

        return $stmt->rowCount();
    }
}
