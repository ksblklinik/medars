<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\ConnectionConfig;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Persistence\PdoBookingRegistrasiRepository;
use App\Infrastructure\Persistence\PdoPasienRepository;
use App\Infrastructure\Persistence\PdoPoliklinikRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Application\Registration\UseCase\CheckInMobileJknUseCase;
use App\Application\Registration\UseCase\ClinicalGuardShieldUseCase;
use App\Application\Registration\UseCase\RegisterInSiteUseCase;
use App\Infrastructure\Realtime\EventMessage;
use App\Infrastructure\Realtime\SseStreamResponse;
use App\Presentation\Http\RegistrationController;
use App\Presentation\Http\Router;
use App\Presentation\View\ViewRenderer;

$router = new Router();
$renderer = new ViewRenderer();

$getRegistrationController = static function () use ($renderer): RegistrationController {
    static $controller = null;
    if ($controller !== null) {
        return $controller;
    }

    $dbDriver = getenv('DB_DRIVER') ?: 'sqlite';
    if ($dbDriver === 'sqlite') {
        $dbDir = __DIR__ . '/../storage';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        $pdo = new PDO("sqlite:{$dbDir}/medars.sqlite");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

    $pasienRepo = new PdoPasienRepository($pdo);
    $poliRepo = new PdoPoliklinikRepository($pdo);
    $regRepo = new PdoRegPeriksaRepository($pdo);
    $bookingRepo = new PdoBookingRegistrasiRepository($pdo);
    $tx = new PdoTransactionManager($pdo);

    $registerInSiteUseCase = new RegisterInSiteUseCase($pasienRepo, $poliRepo, $regRepo, $bookingRepo);
    $checkInMobileJknUseCase = new CheckInMobileJknUseCase($pasienRepo, $poliRepo, $regRepo, $bookingRepo, $tx);
    $clinicalGuardUseCase = new ClinicalGuardShieldUseCase($regRepo);

    $controller = new RegistrationController(
        $pdo,
        $pasienRepo,
        $poliRepo,
        $regRepo,
        $registerInSiteUseCase,
        $checkInMobileJknUseCase,
        $clinicalGuardUseCase,
        $renderer
    );

    return $controller;
};

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

// Modul Pendaftaran Rawat Jalan (PLN-09)
$router->get('/pendaftaran', static function () use ($getRegistrationController): array {
    return $getRegistrationController()->index();
});

$router->get('/api/v1/registration/patients/search', static function () use ($getRegistrationController): array {
    return $getRegistrationController()->apiSearchPatients($_GET);
});

$router->post('/api/v1/registration/insite', static function () use ($getRegistrationController): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    return $getRegistrationController()->apiRegisterInSite($data);
});

$router->post('/api/v1/registration/checkin', static function () use ($getRegistrationController): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    return $getRegistrationController()->apiCheckInMobileJkn($data);
});

$router->get('/api/v1/registration/today', static function () use ($getRegistrationController): array {
    return $getRegistrationController()->apiListToday($_GET);
});

$router->post('/api/v1/registration/shift-queue', static function () use ($getRegistrationController): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    return $getRegistrationController()->apiShiftQueue($data);
});

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
