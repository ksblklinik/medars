<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Database;

use App\Infrastructure\Database\ConnectionConfig;
use App\Infrastructure\Database\ConnectionFactory;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function test_creates_sqlite_connection_with_strict_flags(): void
    {
        $config = new ConnectionConfig(
            driver: 'sqlite',
            database: ':memory:'
        );

        $pdo = ConnectionFactory::create($config);

        $this->assertInstanceOf(\PDO::class, $pdo);
        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
        $this->assertSame(\PDO::FETCH_ASSOC, $pdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function test_generates_correct_mysql_dsn(): void
    {
        $config = new ConnectionConfig(
            driver: 'mysql',
            host: '192.168.1.10',
            port: 3307,
            database: 'sik_db',
            charset: 'utf8mb4'
        );

        $this->assertSame('mysql:host=192.168.1.10;port=3307;dbname=sik_db;charset=utf8mb4', $config->toDsn());
    }
}
