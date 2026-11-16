<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\ConnectionConfig;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Database\InitialSeeder;
use App\Infrastructure\Database\SchemaMigrator;

$dbDriver = getenv('DB_DRIVER') ?: 'sqlite';

if ($dbDriver === 'sqlite') {
    $dbDir = __DIR__ . '/../storage';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }
    $pdo = new PDO("sqlite:{$dbDir}/medars.sqlite");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->exec('PRAGMA foreign_keys = ON;');
} else {
    $config = new ConnectionConfig(
        driver: 'mysql',
        host: getenv('DB_HOST') ?: '127.0.0.1',
        port: (int) (getenv('DB_PORT') ?: 3306),
        database: getenv('DB_DATABASE') ?: 'ksm_next',
        username: getenv('DB_USERNAME') ?: 'root',
        password: getenv('DB_PASSWORD') ?: ''
    );
    $pdo = ConnectionFactory::create($config);
}

$migrator = new SchemaMigrator($pdo);
$migrator->migrate();

$seeder = new InitialSeeder($pdo);
$seeder->seed();

echo "Migrasi skema dan seeder awal berhasil dijalankan ({$dbDriver}).\n";
