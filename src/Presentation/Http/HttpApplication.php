<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Registration\UseCase\CheckInMobileJknUseCase;
use App\Application\Registration\UseCase\ClinicalGuardShieldUseCase;
use App\Application\Registration\UseCase\RegisterInSiteUseCase;
use App\Application\Rme\UseCase\CreateSuratMedisUseCase;
use App\Application\Rme\UseCase\DeleteOdontogramUseCase;
use App\Application\Rme\UseCase\SaveOdontogramUseCase;
use App\Application\Rme\UseCase\SavePrbPrescriptionUseCase;
use App\Application\Rme\UseCase\SaveSoapUseCase;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Domain\Security\Exception\UnauthorizedException;
use App\Domain\Setting\Service\SettingService;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Persistence\PdoAuditLogRepository;
use App\Infrastructure\Persistence\PdoBookingRegistrasiRepository;
use App\Infrastructure\Persistence\PdoIcdGroundingRepository;
use App\Infrastructure\Persistence\PdoOdontogramRepository;
use App\Infrastructure\Persistence\PdoPasienRepository;
use App\Infrastructure\Persistence\PdoPemeriksaanRalanRepository;
use App\Infrastructure\Persistence\PdoPoliklinikRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Infrastructure\Persistence\PdoRmeHistoryRepository;
use App\Infrastructure\Persistence\PdoSettingRepository;
use App\Infrastructure\Persistence\PdoSuratMedisRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use App\Infrastructure\Realtime\EventMessage;
use App\Infrastructure\Realtime\PdoEventBroadcaster;
use App\Infrastructure\Realtime\SseStreamResponse;
use App\Infrastructure\Security\CsrfGuard;
use App\Infrastructure\Security\RbacGuard;
use App\Infrastructure\Security\SessionAuthService;
use App\Presentation\View\ViewRenderer;
use PDO;

final class HttpApplication
{
    private readonly ViewRenderer $renderer;
    private readonly NavigationBuilder $navBuilder;
    private readonly PdoUserRepository $userRepo;
    private readonly PdoAuditLogRepository $auditRepo;
    private readonly SessionAuthService $authService;
    private readonly CsrfGuard $csrfGuard;
    private readonly RbacGuard $rbacGuard;
    private readonly PdoEventBroadcaster $broadcaster;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        private readonly PDO $pdo,
        private array &$session,
    ) {
        $this->renderer = new ViewRenderer();
        $this->navBuilder = new NavigationBuilder();
        $this->userRepo = new PdoUserRepository($this->pdo);
        $this->auditRepo = new PdoAuditLogRepository($this->pdo);
        $this->authService = new SessionAuthService($this->userRepo, $this->auditRepo, $this->session);
        $this->csrfGuard = new CsrfGuard($this->session);
        $this->rbacGuard = new RbacGuard();
        $this->broadcaster = new PdoEventBroadcaster($this->pdo);
        $this->broadcaster->ensureTableExists();
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $postData
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function handle(
        string $method,
        string $uri,
        array $queryParams = [],
        array $postData = [],
        string $rawBody = '',
        array $headers = [],
        string $ipAddress = '127.0.0.1'
    ): array {
        $upperMethod = strtoupper($method);
        $path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');
        if ($path === '//') {
            $path = '/';
        }

        // Parse query string dari URI jika $queryParams kosong
        $queryStr = (string) parse_url($uri, PHP_URL_QUERY);
        if ($queryStr !== '' && $queryParams === []) {
            parse_str($queryStr, $queryParams);
        }

        $isApi = str_starts_with($path, '/api/');

        try {
            $response = $this->dispatchRoute(
                $upperMethod,
                $path,
                $queryParams,
                $postData,
                $rawBody,
                $headers,
                $ipAddress
            );
        } catch (UnauthorizedException $e) {
            if ($isApi) {
                $response = Response::json(['status' => 'error', 'message' => $e->getMessage()], 401)->toArray();
            } else {
                $response = Response::redirect('/auth/login')->toArray();
            }
        } catch (ForbiddenException $e) {
            if ($isApi) {
                $response = Response::json(['status' => 'error', 'message' => $e->getMessage()], 403)->toArray();
            } else {
                $response = Response::html('<h1>403 - Akses Ditolak</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>', 403)->toArray();
            }
        } catch (\InvalidArgumentException $e) {
            $status = $isApi ? 400 : 400;
            $response = $isApi
                ? Response::json(['status' => 'error', 'message' => $e->getMessage()], $status)->toArray()
                : Response::html(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), $status)->toArray();
        } catch (\Throwable $e) {
            error_log('[Medars Error] ' . $e->getMessage());
            $response = $isApi
                ? Response::json(['status' => 'error', 'message' => 'Terjadi kesalahan internal pada server.'], 500)->toArray()
                : Response::html('<h1>500 - Kesalahan Internal Server</h1>', 500)->toArray();
        }

        return $this->applySecurityHeaders($response);
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $postData
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function dispatchRoute(
        string $method,
        string $path,
        array $queryParams,
        array $postData,
        string $rawBody,
        array $headers,
        string $ipAddress
    ): array {
        $router = new Router();

        $authController = new AuthController($this->authService, $this->csrfGuard, $this->renderer);
        $settingService = new SettingService(new PdoSettingRepository($this->pdo));
        $settingsController = new SettingsController(
            $settingService,
            $this->csrfGuard,
            $this->rbacGuard,
            $this->auditRepo,
            $this->renderer
        );
        $usersController = new UsersController(
            $this->userRepo,
            $this->csrfGuard,
            $this->rbacGuard,
            $this->auditRepo,
            $this->renderer
        );

        $pasienRepo = new PdoPasienRepository($this->pdo);
        $poliRepo = new PdoPoliklinikRepository($this->pdo);
        $regRepo = new PdoRegPeriksaRepository($this->pdo);
        $bookingRepo = new PdoBookingRegistrasiRepository($this->pdo);
        $tx = new PdoTransactionManager($this->pdo);

        $registerInSiteUseCase = new RegisterInSiteUseCase($pasienRepo, $poliRepo, $regRepo, $bookingRepo);
        $checkInMobileJknUseCase = new CheckInMobileJknUseCase($pasienRepo, $poliRepo, $regRepo, $bookingRepo, $tx);
        $clinicalGuardUseCase = new ClinicalGuardShieldUseCase($regRepo);

        $regController = new RegistrationController(
            $this->pdo,
            $pasienRepo,
            $poliRepo,
            $regRepo,
            $registerInSiteUseCase,
            $checkInMobileJknUseCase,
            $clinicalGuardUseCase,
            $this->renderer,
            $this->broadcaster,
            $this->navBuilder
        );

        $displayController = new DisplayQueueController(
            $this->pdo,
            $poliRepo,
            $regRepo,
            $this->broadcaster,
            $this->renderer,
            $checkInMobileJknUseCase
        );

        $ralanRepo = new PdoPemeriksaanRalanRepository($this->pdo);
        $historyRepo = new PdoRmeHistoryRepository($this->pdo);
        $icdRepo = new PdoIcdGroundingRepository($this->pdo);
        $odoRepo = new PdoOdontogramRepository($this->pdo);
        $suratRepo = new PdoSuratMedisRepository($this->pdo);

        $dokterRalanController = new DokterRalanController(
            $this->pdo,
            $historyRepo,
            $icdRepo,
            $odoRepo,
            new SaveSoapUseCase($ralanRepo, $regRepo, $tx),
            new SavePrbPrescriptionUseCase($ralanRepo, $regRepo, $tx),
            new SaveOdontogramUseCase($odoRepo),
            new DeleteOdontogramUseCase($odoRepo),
            new CreateSuratMedisUseCase($suratRepo, $tx),
            $this->renderer,
            $this->navBuilder
        );

        // --- RUTE PUBLIK / KIOSK HARDWARE ---
        $router->get('/auth/login', fn(): Response => $authController->showLogin());
        $router->post('/auth/login', fn(): Response => $authController->postLogin($postData, $ipAddress));
        $router->get('/logout', fn(): Response => $authController->logout($ipAddress));
        $router->post('/logout', fn(): Response => $authController->logout($ipAddress));

        $router->get('/display-antrean', fn(): array => $displayController->index());
        $router->get('/antrean', fn(): array => $displayController->index());
        $router->get('/kiosk', fn(): array => $displayController->kiosk($this->csrfGuard->getToken()));
        $router->get('/api/v1/queue/summary', fn(): array => $displayController->apiQueueSummary($queryParams));

        $router->post('/api/v1/kiosk/checkin', function () use ($displayController, $postData, $rawBody, $headers): array {
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $displayController->apiKioskCheckIn($data);
        });

        $router->get('/api/stream', function () use ($queryParams, $headers): array {
            $channel = trim((string) ($queryParams['channel'] ?? 'antrean'));
            if ($channel === '') {
                $channel = 'antrean';
            }

            if (str_starts_with($channel, 'dokter_')) {
                $user = $this->requireAuthenticatedUser();
                $targetDokter = substr($channel, strlen('dokter_'));
                if (!$user->isAdmin() && $user->getUsername() !== $targetDokter) {
                    throw new ForbiddenException('Anda tidak memiliki izin mengakses stream antrean dokter lain.');
                }
            }

            $lastId = isset($headers['Last-Event-ID']) ? (int) $headers['Last-Event-ID'] : 0;
            $body = SseStreamResponse::formatEvent(new EventMessage(
                event: 'system.connected',
                data: ['status' => 'connected', 'channel' => $channel, 'time' => date('c')],
                retry: 3000
            ));

            foreach ($this->broadcaster->fetchSince($channel, $lastId) as $ev) {
                $body .= SseStreamResponse::formatEvent($ev);
            }
            $body .= SseStreamResponse::formatHeartbeat();

            return [
                'status' => 200,
                'headers' => SseStreamResponse::getHeaders(),
                'body' => $body,
            ];
        });

        // --- RUTE TERPROTEKSI SESI, RBAC & CSRF ---
        $renderDashboard = function (): string {
            $user = $this->requireAuthenticatedUser();
            return $this->renderer->render('dashboard', [
                'judul' => 'Dashboard Utama',
                'klinik_nama' => 'SIMRS Medars',
                'current_module' => 'dashboard',
                'module_icon' => 'fa-gauge',
                'user_name' => $user->getFullname() ?: $user->getUsername(),
                'user_role' => $user->getRole(),
                'csrf_token' => $this->csrfGuard->getToken(),
                'navigations' => $this->navBuilder->buildForUser($user),
            ]);
        };
        $router->get('/', $renderDashboard);
        $router->get('/dashboard', $renderDashboard);

        $router->get('/pendaftaran', function () use ($regController): array {
            $user = $this->requireModuleAccess('pendaftaran');
            return $regController->index($user, $this->csrfGuard->getToken());
        });

        $router->get('/api/v1/registration/patients/search', function () use ($regController, $queryParams): array {
            $this->requireModuleAccess('pendaftaran');
            return $regController->apiSearchPatients($queryParams);
        });

        $router->get('/api/v1/registration/today', function () use ($regController, $queryParams): array {
            $this->requireModuleAccess('pendaftaran');
            return $regController->apiListToday($queryParams);
        });

        $router->post('/api/v1/registration/insite', function () use ($regController, $postData, $rawBody, $headers): array {
            $this->requireModuleAccess('pendaftaran');
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $regController->apiRegisterInSite($data);
        });

        $router->post('/api/v1/registration/checkin', function () use ($regController, $postData, $rawBody, $headers): array {
            $this->requireModuleAccess('pendaftaran');
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $regController->apiCheckInMobileJkn($data);
        });

        $router->post('/api/v1/registration/shift-queue', function () use ($regController, $postData, $rawBody, $headers): array {
            $this->requireModuleAccess('pendaftaran');
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $regController->apiShiftQueue($data);
        });

        $router->post('/api/v1/queue/call', function () use ($displayController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['pendaftaran', 'dokter_ralan', 'rawat_jalan', 'igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $displayController->apiCallQueue($data);
        });

        // Users & Settings
        $router->get('/users', fn(): Response => $usersController->index($this->requireAuthenticatedUser()));
        $router->post('/users/save', fn(): Response => $usersController->save($this->requireAuthenticatedUser(), $postData, $ipAddress));
        $router->post('/users/delete', fn(): Response => $usersController->delete(
            $this->requireAuthenticatedUser(),
            (int) ($postData['id'] ?? 0),
            (string) ($postData['_csrf_token'] ?? ''),
            $ipAddress
        ));

        $router->get('/settings', fn(): Response => $settingsController->index($this->requireAuthenticatedUser()));
        $router->post('/settings/save', fn(): Response => $settingsController->save($this->requireAuthenticatedUser(), $postData, $ipAddress));

        // Dokter Rawat Jalan / RME (PLN-14)
        $renderDokterRalan = function () use ($dokterRalanController): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->index($user, $this->csrfGuard->getToken());
        };
        $router->get('/dokter-ralan', $renderDokterRalan);
        $router->get('/rawat-jalan', $renderDokterRalan);

        $router->get('/api/v1/dokter-ralan/queue', function () use ($dokterRalanController, $queryParams): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->apiQueueList($user, $queryParams);
        });
        $router->get('/api/v1/dokter-ralan/history', function () use ($dokterRalanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->apiHistory($queryParams);
        });
        $router->get('/api/v1/dokter-ralan/icd10/search', function () use ($dokterRalanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->apiSearchIcd10($queryParams);
        });
        $router->get('/api/v1/dokter-ralan/odontogram', function () use ($dokterRalanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->apiListOdontogram($queryParams);
        });
        $router->post('/api/v1/dokter-ralan/soap', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiSaveSoap($user, $data);
        });
        $router->post('/api/v1/dokter-ralan/diagnosa', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiSaveDiagnosa($user, $data);
        });
        $router->post('/api/v1/dokter-ralan/prb', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiSavePrb($user, $data);
        });
        $router->post('/api/v1/dokter-ralan/odontogram/save', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiSaveOdontogram($user, $data);
        });
        $router->post('/api/v1/dokter-ralan/odontogram/delete', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiDeleteOdontogram($user, $data);
        });
        $router->post('/api/v1/dokter-ralan/surat-sakit', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiCreateSuratSakit($user, $data);
        });

        return $router->dispatch($method, $path);
    }

    private function requireAuthenticatedUser(): UserAccount
    {
        $user = $this->authService->currentUser();
        if ($user === null) {
            throw new UnauthorizedException('Autentikasi diperlukan.');
        }
        if (!$user->isActive()) {
            throw new UnauthorizedException('Akun pengguna telah dinonaktifkan.');
        }

        return $user;
    }

    private function requireModuleAccess(string $module): UserAccount
    {
        $user = $this->requireAuthenticatedUser();
        $this->rbacGuard->assertCanAccessModule($user, $module);
        return $user;
    }

    /**
     * @param list<string> $modules
     */
    private function requireAnyServiceModuleAccess(array $modules): UserAccount
    {
        $user = $this->requireAuthenticatedUser();
        foreach ($modules as $mod) {
            if ($user->canAccessModule($mod)) {
                return $user;
            }
        }

        throw new ForbiddenException('Anda tidak memiliki izin akses pada modul pelayanan ini.');
    }

    /**
     * @param array<string, mixed> $postData
     * @param array<string, string> $headers
     */
    private function assertCsrfHeaderOrPost(array $postData, array $headers): void
    {
        $headerToken = null;
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'X-CSRF-Token') === 0) {
                $headerToken = $v;
                break;
            }
        }

        $token = $headerToken ?? (isset($postData['_csrf_token']) ? (string) $postData['_csrf_token'] : null);
        if (!$this->csrfGuard->validateToken($token)) {
            throw new ForbiddenException('Token keamanan CSRF tidak valid.');
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(string $rawBody, array $postData, array $headers): array
    {
        $contentType = '';
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Content-Type') === 0) {
                $contentType = strtolower($v);
                break;
            }
        }

        if (str_contains($contentType, 'application/json') && trim($rawBody) !== '') {
            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \InvalidArgumentException('Payload JSON harus berupa objek atau array.');
                }
                return $decoded;
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException('Payload JSON tidak valid: ' . $e->getMessage());
            }
        }

        return $postData;
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string} $response
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function applySecurityHeaders(array $response): array
    {
        $response['headers'] = array_merge($response['headers'], [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);

        return $response;
    }
}
