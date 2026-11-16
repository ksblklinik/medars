<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class DatabaseSmokeTest extends TestCase
{
    public function test_sqlite_in_memory_connection_works(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query('SELECT 1 as val');
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame(1, (int) $result['val']);
    }
}
