<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

final readonly class ConnectionConfig
{
    public function __construct(
        public string $driver = 'mysql',
        public string $host = '127.0.0.1',
        public int $port = 3306,
        public string $database = 'ksm_next',
        public string $username = 'root',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public bool $isReadOnly = false
    ) {}

    public function toDsn(): string
    {
        if ($this->driver === 'sqlite') {
            return "sqlite:{$this->database}";
        }

        return "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}";
    }
}
