<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Database\Exception\ReadOnlyConnectionException;

final class ReadOnlyConnectionGuard
{
    private const ALLOWED_VERBS = ['SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'DESC', 'WITH'];

    private const FORBIDDEN_KEYWORDS = [
        'INSERT',
        'UPDATE',
        'DELETE',
        'REPLACE',
        'DROP',
        'ALTER',
        'TRUNCATE',
        'CREATE',
        'RENAME',
        'GRANT',
        'REVOKE',
        'CALL',
        'EXEC',
        'EXECUTE',
        'MERGE',
        'LOAD',
        'HANDLER',
        'LOCK',
        'UNLOCK',
    ];

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
        // 1. Bersihkan string literal agar nilai teks yang kebetulan memuat kata mutasi tidak memicu false-positive
        $sanitized = (string) preg_replace("/'(?:\\\\.|''|[^'])*'/", "''", $sql);
        $sanitized = (string) preg_replace('/"(?:\\\\.|""|[^"])*"/', '""', $sanitized);

        // 2. Bersihkan komentar SQL (/* ... */, -- ..., # ...)
        $sanitized = (string) preg_replace('/\/\*.*?\*\//s', ' ', $sanitized);
        $sanitized = (string) preg_replace('/(?:--|#)[^\r\n]*/', ' ', $sanitized);

        $trimmed = trim($sanitized);
        if ($trimmed === '') {
            throw ReadOnlyConnectionException::mutationBlocked($sql);
        }

        // 3. Tolak multi-statement (titik koma di tengah query)
        $withoutTrailingSemicolon = rtrim($trimmed, "; \t\n\r");
        if (str_contains($withoutTrailingSemicolon, ';')) {
            throw ReadOnlyConnectionException::mutationBlocked($sql);
        }

        // 4. Pastikan kata kerja pertama termasuk yang diizinkan
        $firstWord = '';
        if (preg_match('/^([A-Za-z_]+)/', $withoutTrailingSemicolon, $matches)) {
            $firstWord = strtoupper($matches[1]);
        }

        if (!in_array($firstWord, self::ALLOWED_VERBS, true)) {
            throw ReadOnlyConnectionException::mutationBlocked($sql);
        }

        // 5. Tolak klausa berbahaya (INTO OUTFILE/DUMPFILE, FOR UPDATE)
        if (preg_match('/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i', $withoutTrailingSemicolon)) {
            throw ReadOnlyConnectionException::mutationBlocked($sql);
        }
        if (preg_match('/\bFOR\s+UPDATE\b/i', $withoutTrailingSemicolon)) {
            throw ReadOnlyConnectionException::mutationBlocked($sql);
        }

        // 6. Tolak kata kunci mutasi/DDL di mana pun di luar string literal (termasuk di dalam CTE)
        $forbiddenPattern = '/\b(?:' . implode('|', self::FORBIDDEN_KEYWORDS) . ')\b/i';
        if (preg_match($forbiddenPattern, $withoutTrailingSemicolon)) {
            throw ReadOnlyConnectionException::mutationBlocked($sql);
        }
    }
}
