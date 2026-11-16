<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Repository\BridgingQueueRepositoryInterface;
use PDO;

final readonly class PdoBridgingQueueRepository implements BridgingQueueRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function enqueue(
        string $provider,
        string $resourceType,
        string $noRawat,
        string $endpoint,
        string $payloadJson,
    ): array {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "INSERT INTO bridging_sync_queue (provider, resource_type, no_rawat, endpoint, payload_json, status, attempts, created_at, updated_at)
             VALUES (:prov, :res, :nr, :ep, :payload, 'Pending', 0, :created, :updated)"
        );
        $stmt->execute([
            ':prov' => $provider,
            ':res' => $resourceType,
            ':nr' => $noRawat,
            ':ep' => $endpoint,
            ':payload' => $payloadJson,
            ':created' => $now,
            ':updated' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $rowStmt = $this->pdo->prepare('SELECT * FROM bridging_sync_queue WHERE id = :id LIMIT 1');
        $rowStmt->execute([':id' => $id]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : ['id' => $id, 'status' => 'Pending', 'payload_json' => $payloadJson];
    }

    public function fetchPending(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM bridging_sync_queue WHERE status = 'Pending' ORDER BY id ASC LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markSynced(int $id, string $externalId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE bridging_sync_queue
             SET status = 'Synced', attempts = attempts + 1, external_id = :ext, updated_at = :upd
             WHERE id = :id"
        );
        $stmt->execute([
            ':ext' => $externalId,
            ':upd' => date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE bridging_sync_queue
             SET status = 'Failed', attempts = attempts + 1, last_error = :err, updated_at = :upd
             WHERE id = :id"
        );
        $stmt->execute([
            ':err' => $error,
            ':upd' => date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function listRecent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bridging_sync_queue ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
