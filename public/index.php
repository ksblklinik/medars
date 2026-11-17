<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\ConnectionConfig;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Database\SchemaMigrator;
use App\Presentation\Http\HttpApplication;

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$getPdo = static function (): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

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
        try {
            $pdo->query('SELECT 1 FROM mlite_smartagent_profiles LIMIT 1');
        } catch (Throwable) {
            (new SchemaMigrator($pdo))->migrate();
        }
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

    return $pdo;
};

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');

$headers = [];
if (function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        $headers[(string) $name] = (string) $value;
    }
}
if (!isset($headers['X-CSRF-Token']) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $headers['X-CSRF-Token'] = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
}
if (!isset($headers['Content-Type']) && isset($_SERVER['CONTENT_TYPE'])) {
    $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
}
if (!isset($headers['Last-Event-ID']) && isset($_SERVER['HTTP_LAST_EVENT_ID'])) {
    $headers['Last-Event-ID'] = (string) $_SERVER['HTTP_LAST_EVENT_ID'];
}

$rawBody = (string) file_get_contents('php://input');
$ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

$app = new HttpApplication($getPdo(), $_SESSION);

$response = $app->handle(
    method: $method,
    uri: $uri,
    queryParams: $_GET,
    postData: $_POST,
    rawBody: $rawBody,
    headers: $headers,
    ipAddress: $ipAddress
);

// Lepaskan session lock sebelum output stream SSE agar tab browser lain tidak terblokir
if ($path === '/api/stream' && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!headers_sent()) {
    http_response_code($response['status']);
    foreach ($response['headers'] as $name => $value) {
        header("{$name}: {$value}");
    }
}

echo $response['body'];
