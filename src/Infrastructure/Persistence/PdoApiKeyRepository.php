<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Security\Repository\ApiKeyRepositoryInterface;
use PDO;

final class PdoApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listKeys(string $search = ''): array
    {
        $sql = 'SELECT id, label, username, key_prefix, methods, ip_range, exp_time, is_active, created_at
                FROM mlite_api_keys';
        $params = [];
        $trimmed = trim($search);
        if ($trimmed !== '') {
            $sql .= ' WHERE label LIKE :search OR username LIKE :search OR key_prefix LIKE :search';
            $params[':search'] = '%' . $trimmed . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (int) $row['is_active'] === 1;
            return $row;
        }, $rows ?: []);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createKey(array $data): array
    {
        $rawKey = 'ksm_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $rawKey);
        $prefix = substr($rawKey, 0, 12) . '...';
        $now = date('Y-m-d H:i:s');

        $label = trim((string) ($data['label'] ?? 'API Key'));
        $username = trim((string) ($data['username'] ?? ''));
        $methods = trim((string) ($data['methods'] ?? 'GET'));
        $ipRange = trim((string) ($data['ip_range'] ?? '*'));
        $expTime = trim((string) ($data['exp_time'] ?? ''));

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_api_keys (label, username, api_key_hash, key_prefix, methods, ip_range, exp_time, is_active, created_at)
             VALUES (:label, :username, :api_key_hash, :key_prefix, :methods, :ip_range, :exp_time, 1, :created_at)'
        );
        $stmt->execute([
            ':label' => $label,
            ':username' => $username,
            ':api_key_hash' => $hash,
            ':key_prefix' => $prefix,
            ':methods' => $methods !== '' ? $methods : 'GET',
            ':ip_range' => $ipRange !== '' ? $ipRange : '*',
            ':exp_time' => $expTime,
            ':created_at' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'label' => $label,
            'username' => $username,
            'key_prefix' => $prefix,
            'methods' => $methods !== '' ? $methods : 'GET',
            'ip_range' => $ipRange !== '' ? $ipRange : '*',
            'exp_time' => $expTime,
            'is_active' => true,
            'created_at' => $now,
            'raw_key' => $rawKey,
        ];
    }

    public function toggleActive(int $id, bool $active): bool
    {
        $stmt = $this->pdo->prepare('UPDATE mlite_api_keys SET is_active = :active WHERE id = :id');
        $stmt->execute([
            ':active' => $active ? 1 : 0,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteKey(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM mlite_api_keys WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyKey(string $rawKey): ?array
    {
        $trimmed = trim($rawKey);
        if ($trimmed === '') {
            return null;
        }
        $hash = hash('sha256', $trimmed);
        $stmt = $this->pdo->prepare(
            'SELECT id, label, username, key_prefix, methods, ip_range, exp_time, is_active, created_at
             FROM mlite_api_keys
             WHERE api_key_hash = :hash AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $expTime = trim((string) ($row['exp_time'] ?? ''));
        if ($expTime !== '' && strtotime($expTime) !== false && strtotime($expTime) < time()) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['is_active'] = true;
        return $row;
    }
}
