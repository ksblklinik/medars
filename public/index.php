<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Realtime\EventMessage;
use App\Infrastructure\Realtime\SseStreamResponse;
use App\Presentation\Http\Router;
use App\Presentation\View\ViewRenderer;

$router = new Router();
$renderer = new ViewRenderer();

$renderDashboard = static function () use ($renderer): string {
    return $renderer->render('dashboard', [
        'judul' => 'Dashboard Utama',
        'klinik_nama' => 'Klinik Pratama Rawat Inap',
        'current_module' => 'dashboard',
        'module_icon' => 'fa-gauge',
        'user_name' => 'Administrator',
        'user_role' => 'Admin',
        'navigations' => [
            ['title' => 'Dashboard', 'icon' => 'fa-gauge', 'route' => '/'],
            ['title' => 'Pendaftaran', 'icon' => 'fa-user-plus', 'route' => '/pendaftaran'],
            ['title' => 'Rawat Jalan', 'icon' => 'fa-stethoscope', 'route' => '/rawat-jalan'],
            ['title' => 'Farmasi', 'icon' => 'fa-pills', 'route' => '/farmasi'],
            ['title' => 'Kasir', 'icon' => 'fa-cash-register', 'route' => '/kasir'],
        ],
    ]);
};

$router->get('/', $renderDashboard);
$router->get('/dashboard', $renderDashboard);

// Endpoint Server-Sent Events (SSE)
$router->get('/api/stream', static function (): string {
    foreach (SseStreamResponse::getHeaders() as $headerName => $headerValue) {
        header("{$headerName}: {$headerValue}");
    }

    $connectedMsg = new EventMessage(
        event: 'system.connected',
        data: ['status' => 'connected', 'time' => date('c')],
        retry: 3000
    );

    echo SseStreamResponse::formatEvent($connectedMsg);
    echo SseStreamResponse::formatHeartbeat();

    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    exit;
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$response = $router->dispatch($method, $uri);

http_response_code($response['status']);
foreach ($response['headers'] as $name => $value) {
    header("{$name}: {$value}");
}

echo $response['body'];
