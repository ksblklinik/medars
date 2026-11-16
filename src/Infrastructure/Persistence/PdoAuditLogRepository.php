<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use PDO;

final class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function log(int $userId, string $username, string $action, string $details, string $ipAddress): void
    {
        $sql = "
            INSERT INTO mlite_logs (user_id, username, action, details, ip_address, created_at)
            VALUES (:user_id, :username, :action, :details, :ip_address, :created_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => $ipAddress,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getRecentLogs(int $limit = 50, ?string $action = null): array
    {
        if ($action !== null && $action !== '') {
            $stmt = $this->pdo->prepare("
                SELECT * FROM mlite_logs
                WHERE action = :action
                ORDER BY id DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':action', $action, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM mlite_logs
                ORDER BY id DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
