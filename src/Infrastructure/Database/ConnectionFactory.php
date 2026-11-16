<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

final class ConnectionFactory
{
    /**
     * Membuat instance PDO dengan standar keamanan ketat dan preservasi tipe data asli.
     */
    public static function create(ConnectionConfig $config): \PDO
    {
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false, // Memaksa native MySQL prepares (anti auto-cast string number)
        ];

        if ($config->driver === 'mysql') {
            $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$config->charset} COLLATE {$config->charset}_unicode_ci, sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'";
        }

        $pdo = new \PDO(
            $config->toDsn(),
            $config->driver === 'sqlite' ? null : $config->username,
            $config->driver === 'sqlite' ? null : $config->password,
            $options
        );

        return $pdo;
    }
}
