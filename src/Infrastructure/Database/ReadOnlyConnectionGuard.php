<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Database\Exception\ReadOnlyConnectionException;

final class ReadOnlyConnectionGuard
{
    private const ALLOWED_VERBS = ['SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'DESC'];

    public function __construct(
        private readonly \PDO $pdo
    ) {}

    /**
     * Menjalankan kueri baca aman (SELECT/SHOW/DESCRIBE).
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        $this->assertReadOnly($sql);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        /** @var array<int, array<string, mixed>> $result */
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * Mengeksekusi perintah. Otomatis menolak mutasi (INSERT, UPDATE, DELETE, DROP, ALTER).
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @return int
     * @throws ReadOnlyConnectionException
     */
    public function execute(string $sql, array $params = []): int
    {
        $this->assertReadOnly($sql);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    private function assertReadOnly(string $sql): void
    {
        $trimmed = ltrim($sql);
        $firstWord = strtoupper((string) preg_replace('/^(\w+).*/s', '$1', $trimmed));

        if (!in_array($firstWord, self::ALLOWED_VERBS, true)) {
            throw ReadOnlyConnectionException::mutationBlocked($sql);
        }
    }
}
