<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Billing\UseCase\ProcessPosApotekUmumUseCase;
use App\Application\Billing\UseCase\ProcessSplitPaymentUseCase;
use App\Application\Billing\UseCase\SettlePiutangUseCase;
use App\Application\Billing\UseCase\ReconcilePendingOrderUseCase;
use App\Application\Billing\UseCase\VoidBillingTransactionUseCase;
use App\Application\Bridging\UseCase\ProcessBridgingQueueUseCase;
use App\Application\Bridging\UseCase\QueueSatuSehatSyncUseCase;
use App\Application\Bridging\UseCase\VerifyVedikaClaimUseCase;
use App\Application\Communication\UseCase\AssignHomeCarePreceptorUseCase;
use App\Application\Communication\UseCase\ProcessInboundWaWebhookUseCase;
use App\Application\Communication\UseCase\SendWhatsAppMessageUseCase;
use App\Application\Pharmacy\UseCase\CreateResepUseCase;
use App\Application\Pharmacy\UseCase\DeleteResepUseCase;
use App\Application\Pharmacy\UseCase\DispenseResepUseCase;
use App\Application\Pharmacy\UseCase\SaveCopyResepUseCase;
use App\Application\Registration\UseCase\CheckInMobileJknUseCase;
use App\Application\Registration\UseCase\ClinicalGuardShieldUseCase;
use App\Application\Registration\UseCase\DeleteMasterPasienUseCase;
use App\Application\Registration\UseCase\RegisterInSiteUseCase;
use App\Application\Registration\UseCase\SaveMasterPasienUseCase;
use App\Application\Registration\UseCase\UpdateRegistrationStatusUseCase;
use App\Application\Rme\UseCase\CreateSuratMedisUseCase;
use App\Application\Rme\UseCase\DeleteOdontogramUseCase;
use App\Application\Rme\UseCase\DeleteTindakanDokterUseCase;
use App\Application\Rme\UseCase\SaveOdontogramUseCase;
use App\Application\Rme\UseCase\SavePrbPrescriptionUseCase;
use App\Application\Rme\UseCase\SaveResumeMedisUseCase;
use App\Application\Rme\UseCase\SaveSoapUseCase;
use App\Application\Rme\UseCase\SaveTindakanDokterUseCase;
use App\Application\Support\UseCase\CreateLabOrderUseCase;
use App\Application\Support\UseCase\CreateRadiologyOrderUseCase;
use App\Application\Support\UseCase\DeleteLabOrderUseCase;
use App\Application\Support\UseCase\SaveLabResultUseCase;
use App\Application\Support\UseCase\SaveRadiologyResultUseCase;
use App\Domain\Audit\Service\DataIntegrityAuditService;
use App\Domain\Audit\Service\LogRotationService;
use App\Domain\Bridging\Service\BpjsGatewayService;
use App\Domain\Bridging\Service\HfisLeanCalculator;
use App\Domain\Bridging\Service\SatuSehatAuthService;
use App\Domain\Bridging\ValueObject\BridgingCredentials;
use App\Domain\Bridging\ValueObject\TimeoutPolicy;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Domain\Security\Exception\UnauthorizedException;
use App\Domain\Setting\Service\SettingService;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Communication\CurlWaClient;
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Persistence\PdoAuditLogRepository;
use App\Infrastructure\Persistence\PdoBillingLedgerRepository;
use App\Infrastructure\Persistence\PdoBookingRegistrasiRepository;
use App\Infrastructure\Persistence\PdoBridgingQueueRepository;
use App\Infrastructure\Persistence\PdoDataBarangRepository;
use App\Infrastructure\Persistence\PdoDataIntegrityAuditRepository;
use App\Infrastructure\Persistence\PdoDokterTindakanRepository;
use App\Infrastructure\Persistence\PdoGudangBarangRepository;
use App\Infrastructure\Persistence\PdoHomeCareRepository;
use App\Infrastructure\Persistence\PdoIcdGroundingRepository;
use App\Infrastructure\Persistence\PdoLabRadiologyRepository;
use App\Infrastructure\Persistence\PdoLogRotationRepository;
use App\Infrastructure\Persistence\PdoOdontogramRepository;
use App\Infrastructure\Persistence\PdoOperationalReportRepository;
use App\Infrastructure\Persistence\PdoPasienRepository;
use App\Infrastructure\Persistence\PdoPemeriksaanRalanRepository;
use App\Infrastructure\Persistence\PdoPoliklinikRepository;
use App\Infrastructure\Persistence\PdoPosApotekUmumRepository;
use App\Infrastructure\Persistence\PdoRegPeriksaRepository;
use App\Infrastructure\Persistence\PdoResepRepository;
use App\Infrastructure\Persistence\PdoRmeHistoryRepository;
use App\Infrastructure\Persistence\PdoSettingRepository;
use App\Infrastructure\Persistence\PdoSuratMedisRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use App\Infrastructure\Persistence\PdoVedikaRepository;
use App\Infrastructure\Persistence\PdoWaGatewayRepository;
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
            $this->navBuilder,
            new UpdateRegistrationStatusUseCase($regRepo, $clinicalGuardUseCase, $tx)
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
        $tindakanRepo = new PdoDokterTindakanRepository($this->pdo);
        $ledgerRepo = new PdoBillingLedgerRepository($this->pdo);

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
            $this->navBuilder,
            $tindakanRepo,
            new SaveTindakanDokterUseCase($tindakanRepo, $ledgerRepo, $tx),
            new DeleteTindakanDokterUseCase($tindakanRepo, $ledgerRepo, $tx),
            new SaveResumeMedisUseCase($tindakanRepo, $tx)
        );

        $barangRepo = new PdoDataBarangRepository($this->pdo);
        $gudangRepo = new PdoGudangBarangRepository($this->pdo);
        $resepRepo = new PdoResepRepository($this->pdo);

        $pharmacyController = new PharmacyController(
            $this->pdo,
            $barangRepo,
            $gudangRepo,
            new CreateResepUseCase($resepRepo, $tx),
            new DispenseResepUseCase($resepRepo, $barangRepo, $gudangRepo, $tx),
            new DeleteResepUseCase($resepRepo, $tx),
            new SaveCopyResepUseCase($resepRepo, $tx),
            $this->renderer,
            $this->navBuilder
        );

        $posRepo = new PdoPosApotekUmumRepository($this->pdo);

        $billingController = new BillingController(
            $this->pdo,
            $ledgerRepo,
            new ProcessSplitPaymentUseCase($ledgerRepo, $regRepo, $tx),
            new VoidBillingTransactionUseCase($ledgerRepo, $regRepo, $this->auditRepo, $tx),
            new ReconcilePendingOrderUseCase($ledgerRepo, $this->auditRepo, $tx),
            new ProcessPosApotekUmumUseCase($barangRepo, $gudangRepo, $posRepo, $tx),
            $this->renderer,
            $this->navBuilder,
            new SettlePiutangUseCase($ledgerRepo, $tx)
        );

        $labRepo = new PdoLabRadiologyRepository($this->pdo);
        $reportRepo = new PdoOperationalReportRepository($this->pdo);
        $supportController = new SupportController(
            $labRepo,
            $reportRepo,
            new CreateLabOrderUseCase($labRepo, $ledgerRepo, $tx),
            new SaveLabResultUseCase($labRepo, $ledgerRepo, $tx),
            new DeleteLabOrderUseCase($labRepo, $tx, $ledgerRepo),
            new CreateRadiologyOrderUseCase($labRepo, $ledgerRepo, $tx),
            new SaveRadiologyResultUseCase($labRepo, $tx),
            $this->renderer,
            $this->navBuilder,
            new \App\Application\Support\UseCase\DeleteRadiologyOrderUseCase($labRepo, $tx, $ledgerRepo)
        );

        $bridgingQueueRepo = new PdoBridgingQueueRepository($this->pdo);
        $httpClient = new CurlHttpClient();
        $timeoutPolicy = new TimeoutPolicy(2, 4);
        $bpjsCreds = new BridgingCredentials(
            (string) $settingService->get('bridging', 'bpjs_consid', '-'),
            (string) $settingService->get('bridging', 'bpjs_secretkey', '-'),
            (string) $settingService->get('bridging', 'bpjs_user_key', '-'),
            (string) $settingService->get('bridging', 'bpjs_url', '-'),
        );
        $satuSehatCreds = new BridgingCredentials(
            (string) $settingService->get('bridging', 'satusehat_org_id', '-'),
            (string) $settingService->get('bridging', 'satusehat_client_id', '-'),
            (string) $settingService->get('bridging', 'satusehat_client_secret', '-'),
            (string) $settingService->get('bridging', 'satusehat_url', '-'),
        );
        $satuSehatAuth = new SatuSehatAuthService(
            (string) $settingService->get('bridging', 'satusehat_auth_url', '-'),
            (string) $settingService->get('bridging', 'satusehat_client_id', '-'),
            (string) $settingService->get('bridging', 'satusehat_client_secret', '-'),
            $httpClient,
            $timeoutPolicy,
        );
        $vedikaRepo = new PdoVedikaRepository($this->pdo);
        $bridgingController = new BridgingController(
            $bridgingQueueRepo,
            new BpjsGatewayService($bpjsCreds, $httpClient, $timeoutPolicy),
            new HfisLeanCalculator(),
            new QueueSatuSehatSyncUseCase($bridgingQueueRepo),
            new ProcessBridgingQueueUseCase($bridgingQueueRepo, $httpClient, $satuSehatCreds, $timeoutPolicy, $satuSehatAuth),
            new VerifyVedikaClaimUseCase($vedikaRepo, $tx),
            $this->renderer,
            $this->navBuilder,
            $vedikaRepo
        );
        $vclaimController = new VclaimController(new \App\Infrastructure\Persistence\PdoVclaimRepository($this->pdo));
        $pcareController = new PcareController(
            new \App\Infrastructure\Persistence\PdoPcareRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );
        $hfisAntrolController = new HfisAntrolController(new \App\Infrastructure\Persistence\PdoHfisAntrolRepository($this->pdo));
        $jknMobileController = new JknMobileController(
            new \App\Infrastructure\Persistence\PdoJknMobileRepository($this->pdo),
            (string) ($this->config['app_key'] ?? 'medars-jkn-secret')
        );
        $jknMobileFktpController = new JknMobileFktpController(
            new \App\Infrastructure\Persistence\PdoJknMobileRepository($this->pdo),
            (string) ($this->config['app_key'] ?? 'medars-jkn-secret')
        );
        $satuSehatController = new SatuSehatController(new \App\Infrastructure\Persistence\PdoSatuSehatMappingRepository($this->pdo));

        $waRepo = new PdoWaGatewayRepository($this->pdo);
        $homeCareRepo = new PdoHomeCareRepository($this->pdo);
        $waClient = new CurlWaClient(
            (string) $settingService->get('wagateway', 'api_url', '-'),
            (string) $settingService->get('wagateway', 'api_token', '-')
        );
        $communicationController = new CommunicationController(
            $waRepo,
            $homeCareRepo,
            new SendWhatsAppMessageUseCase($waRepo, $waClient),
            new ProcessInboundWaWebhookUseCase($waRepo, $waClient),
            new AssignHomeCarePreceptorUseCase($homeCareRepo),
            $this->renderer,
            $this->navBuilder
        );

        $pasienController = new PasienController(
            $pasienRepo,
            new SaveMasterPasienUseCase($pasienRepo, $tx),
            new DeleteMasterPasienUseCase($pasienRepo, $tx),
            $this->renderer,
            $this->navBuilder
        );

        $masterController = new MasterController(
            new \App\Infrastructure\Persistence\PdoMasterDataRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $suratController = new SuratController(
            $suratRepo,
            new CreateSuratMedisUseCase($suratRepo, $tx),
            $this->renderer,
            $this->navBuilder
        );

        $kepegawaianController = new KepegawaianController(
            new \App\Infrastructure\Persistence\PdoMasterDataRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $frontOfficeController = new FrontOfficeController(
            new \App\Infrastructure\Persistence\PdoFrontOfficeRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $igdController = new IgdController(
            new \App\Infrastructure\Persistence\PdoIgdRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $ranapRepo = new \App\Infrastructure\Persistence\PdoRawatInapRepository($this->pdo);
        $ranapController = new RawatInapController(
            $ranapRepo,
            new \App\Application\Ranap\UseCase\ManageKamarInapUseCase($ranapRepo, $ledgerRepo, $tx),
            $this->renderer,
            $this->navBuilder
        );

        $dokterRanapController = new DokterRanapController(
            new \App\Infrastructure\Persistence\PdoDokterRanapRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $gudangFarmasiController = new GudangFarmasiController(
            new \App\Infrastructure\Persistence\PdoGudangFarmasiRepository($this->pdo, $tx),
            $this->renderer,
            $this->navBuilder
        );

        $keuanganController = new KeuanganController(
            new \App\Infrastructure\Persistence\PdoKeuanganRepository($this->pdo, $ledgerRepo, $tx),
            $this->renderer,
            $this->navBuilder
        );

        $jasaMedisController = new JasaMedisController(
            new \App\Infrastructure\Persistence\PdoJasaMedisRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $manajemenController = new ManajemenController(
            new \App\Infrastructure\Persistence\PdoManajemenAnalyticsRepository($this->pdo, new HfisLeanCalculator()),
            $reportRepo,
            $this->renderer,
            $this->navBuilder
        );

        $profilController = new ProfilController(
            $this->userRepo,
            $this->renderer,
            $this->navBuilder
        );

        $portalPasienController = new PortalPasienController(
            new \App\Infrastructure\Persistence\PdoPortalPasienRepository($this->pdo),
            $this->renderer
        );

        $presensiController = new PresensiController(
            new \App\Infrastructure\Persistence\PdoPresensiRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $inventarisController = new InventarisController(
            new \App\Infrastructure\Persistence\PdoInventarisRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $apiKeyController = new ApiKeyController(
            new \App\Infrastructure\Persistence\PdoApiKeyRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $moduleManagerController = new ModuleManagerController(
            new \App\Infrastructure\Persistence\PdoModuleRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $verifikasiController = new VerifikasiController(
            new \App\Application\Rme\UseCase\VerifySuratUseCase($suratRepo),
            $this->renderer
        );

        $penjualanController = new PenjualanController(
            new \App\Infrastructure\Persistence\PdoPenjualanRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $operasiController = new OperasiController(
            new \App\Infrastructure\Persistence\PdoOperasiRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $ruangTindakanController = new RuangTindakanController(
            new \App\Infrastructure\Persistence\PdoRuangTindakanRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $utdController = new UtdController(
            new \App\Infrastructure\Persistence\PdoUtdRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $veronisaController = new VeronisaController(
            new \App\Infrastructure\Persistence\PdoVeronisaRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $chatController = new ChatController(
            new \App\Infrastructure\Persistence\PdoChatRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $orthancController = new OrthancController(
            new \App\Infrastructure\Persistence\PdoOrthancRepository($this->pdo),
            $this->renderer,
            $this->navBuilder
        );

        $smartAgentRepo = new \App\Infrastructure\Persistence\PdoSmartAgentRepository($this->pdo);
        $smartAgentController = new SmartAgentController(
            $smartAgentRepo,
            new \App\Application\Rme\UseCase\RecommendClinicalPdtUseCase($smartAgentRepo, $icdRepo),
            $this->renderer,
            $this->navBuilder
        );

        $icareController = new IcareController(
            new \App\Application\Bridging\UseCase\FetchIcareHistoryUseCase(
                new \App\Infrastructure\Persistence\PdoIcareRepository($this->pdo)
            )
        );

        $auditController = new AuditController(
            new DataIntegrityAuditService(new PdoDataIntegrityAuditRepository($this->pdo)),
            new LogRotationService(new PdoLogRotationRepository($this->pdo)),
            $this->renderer,
            $this->navBuilder,
            $this->auditRepo
        );

        // --- RUTE PUBLIK / KIOSK HARDWARE ---
        $router->get('/favicon.ico', fn(): array => [
            'status' => 204,
            'headers' => ['Content-Type' => 'image/x-icon'],
            'body' => '',
        ]);
        $router->get('/auth/login', fn(): Response => $authController->showLogin());
        $router->post('/auth/login', fn(): Response => $authController->postLogin($postData, $ipAddress));
        $router->get('/logout', fn(): Response => $authController->logout($ipAddress));
        $router->post('/logout', fn(): Response => $authController->logout($ipAddress));

        $router->get('/display-antrean', fn(): array => $displayController->index());
        $router->get('/antrean', fn(): array => $displayController->index());
        $router->get('/kiosk', fn(): array => $displayController->kiosk($this->csrfGuard->getToken()));
        $router->get('/portal-pasien', fn(): array => $portalPasienController->index());
        $router->post('/api/v1/portal-pasien/lookup', function () use ($portalPasienController, $postData, $rawBody, $headers): array {
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $portalPasienController->apiLookup($data);
        });
        $router->post('/api/v1/portal-pasien/booking', function () use ($portalPasienController, $postData, $rawBody, $headers): array {
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $portalPasienController->apiBooking($data);
        });
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
            $today = date('Y-m-d');
            $safeCount = function (string $sql, array $params = []): int {
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    return (int) $stmt->fetchColumn();
                } catch (\Throwable) {
                    return 0;
                }
            };

            $stats = [
                'visits_today' => $safeCount('SELECT COUNT(*) FROM reg_periksa WHERE tgl_registrasi = :d', [':d' => $today]),
                'soap_done' => $safeCount("SELECT COUNT(*) FROM reg_periksa WHERE tgl_registrasi = :d AND stts = 'Sudah'", [':d' => $today]),
                'resep_pending' => $safeCount("SELECT COUNT(*) FROM resep_obat WHERE tgl_peresepan = :d AND (tgl_perawatan = '0000-00-00' OR tgl_perawatan IS NULL)", [':d' => $today]),
                'kasir_pending' => $safeCount("SELECT COUNT(DISTINCT no_rawat) FROM mlite_billing_ledger WHERE status = 'Pending'"),
            ];

            $dashboardSummaryRepo = new \App\Infrastructure\Persistence\PdoDashboardSummaryRepository($this->pdo);

            return $this->renderer->render('dashboard', [
                'judul' => 'Dashboard Utama',
                'klinik_nama' => 'SIMRS Medars',
                'current_module' => 'dashboard',
                'module_icon' => 'fa-gauge',
                'user_name' => $user->getFullname() ?: $user->getUsername(),
                'user_role' => $user->getRole(),
                'csrf_token' => $this->csrfGuard->getToken(),
                'navigations' => $this->navBuilder->buildForUser($user),
                'dashboard_stats' => $stats,
                'dashboard_trends' => $dashboardSummaryRepo->getSevenDayTrends(),
                'dashboard_announcements' => $dashboardSummaryRepo->listAnnouncements(8),
            ]);
        };
        $router->get('/', $renderDashboard);
        $router->get('/dashboard', $renderDashboard);

        $router->post('/api/v1/dashboard/announcements/create', function () use ($postData, $rawBody, $headers): array {
            $user = $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            $judul = trim((string) ($data['judul'] ?? ''));
            $isi = trim((string) ($data['isi'] ?? ''));
            if ($judul === '' || $isi === '') {
                return [
                    'status' => 422,
                    'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                    'body' => json_encode(['status' => 'error', 'message' => 'Judul dan isi pengumuman wajib diisi.'], JSON_UNESCAPED_UNICODE),
                ];
            }
            $repo = new \App\Infrastructure\Persistence\PdoDashboardSummaryRepository($this->pdo);
            $created = $repo->createAnnouncement([
                'judul' => $judul,
                'isi' => $isi,
                'prioritas' => (string) ($data['prioritas'] ?? 'Info'),
                'penulis' => $user->getFullname() ?: $user->getUsername(),
            ]);
            return [
                'status' => 201,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => json_encode(['status' => 'success', 'data' => $created], JSON_UNESCAPED_UNICODE),
            ];
        });

        $router->post('/api/v1/dashboard/announcements/delete', function () use ($postData, $rawBody, $headers): array {
            $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            $id = (int) ($data['id'] ?? 0);
            $repo = new \App\Infrastructure\Persistence\PdoDashboardSummaryRepository($this->pdo);
            $ok = $id > 0 && $repo->deleteAnnouncement($id);
            return [
                'status' => $ok ? 200 : 404,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => json_encode(['status' => $ok ? 'success' : 'error'], JSON_UNESCAPED_UNICODE),
            ];
        });

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
        $router->post('/api/v1/registration/update-status', function () use ($regController, $postData, $rawBody, $headers): array {
            $this->requireModuleAccess('pendaftaran');
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $regController->apiUpdateStatus($data);
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
        $router->get('/pendaftaran/cetak', function () use ($regController, $queryParams): array {
            $this->requireModuleAccess('pendaftaran');
            return $regController->printAntrean($queryParams);
        });
        $router->post('/api/v1/registration/change-poli-dokter', function () use ($regController, $postData, $rawBody, $headers): array {
            $this->requireModuleAccess('pendaftaran');
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $regController->apiChangePoliDokter($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/registration/berkas', function () use ($regController, $queryParams): array {
            $this->requireModuleAccess('pendaftaran');
            return $regController->apiBerkasList($queryParams);
        });
        $router->post('/api/v1/registration/berkas-upload', function () use ($regController, $postData, $rawBody, $headers): array {
            $this->requireModuleAccess('pendaftaran');
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $regController->apiBerkasUpload($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/registration/berkas-delete', function () use ($regController, $postData, $rawBody, $headers): array {
            $this->requireModuleAccess('pendaftaran');
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $regController->apiBerkasDelete($this->parseJsonOrPost($rawBody, $postData, $headers));
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
        $router->get('/api/v1/dokter-ralan/tindakan/search', function () use ($dokterRalanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->apiSearchTindakan($queryParams);
        });
        $router->get('/api/v1/dokter-ralan/tindakan', function () use ($dokterRalanController, $queryParams): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->apiListTindakan($user, $queryParams);
        });
        $router->post('/api/v1/dokter-ralan/tindakan/save', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiSaveTindakan($user, $data);
        });
        $router->post('/api/v1/dokter-ralan/tindakan/delete', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiDeleteTindakan($user, $data);
        });
        $router->get('/api/v1/dokter-ralan/resume', function () use ($dokterRalanController, $queryParams): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->apiGetResume($user, $queryParams);
        });
        $router->post('/api/v1/dokter-ralan/resume', function () use ($dokterRalanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRalanController->apiSaveResume($user, $data);
        });
        $router->get('/dokter-ralan/resume/cetak', function () use ($dokterRalanController, $queryParams): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->printResumeMedis($user, $queryParams);
        });
        $router->get('/dokter-ralan/eresep/cetak', function () use ($dokterRalanController, $queryParams): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->printEresep($user, $queryParams);
        });
        $router->get('/api/v1/dokter-ralan/full-history', function () use ($dokterRalanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ralan', 'rawat_jalan']);
            return $dokterRalanController->apiFullHistory($queryParams);
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

        // Farmasi & Validasi E-Resep (PLN-18)
        $renderPharmacy = function () use ($pharmacyController): array {
            $user = $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            return $pharmacyController->index($user, $this->csrfGuard->getToken());
        };
        $router->get('/farmasi', $renderPharmacy);
        $router->get('/apotek-ralan', $renderPharmacy);
        $router->get('/farmasi/etiket', function () use ($pharmacyController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            return $pharmacyController->printEtiket($queryParams);
        });
        $router->get('/farmasi/cetak-resep', function () use ($pharmacyController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'dokter_ralan']);
            return $pharmacyController->printResep($queryParams);
        });

        $router->get('/api/v1/farmasi/queue', function () use ($pharmacyController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            return $pharmacyController->apiQueueResep($queryParams);
        });
        $router->get('/api/v1/farmasi/resep/detail', function () use ($pharmacyController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'dokter_ralan', 'rawat_jalan']);
            return $pharmacyController->apiResepDetail($queryParams);
        });
        $router->get('/api/v1/farmasi/obat/search', function () use ($pharmacyController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'dokter_ralan', 'rawat_jalan']);
            return $pharmacyController->apiSearchObat($queryParams);
        });
        $router->post('/api/v1/farmasi/resep/create', function () use ($pharmacyController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $pharmacyController->apiCreateResep($user, $data);
        });
        $router->post('/api/v1/farmasi/resep/dispense', function () use ($pharmacyController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $pharmacyController->apiDispenseResep($user, $data);
        });
        $router->post('/api/v1/farmasi/resep/delete', function () use ($pharmacyController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $pharmacyController->apiDeleteResep($data);
        });
        $router->post('/api/v1/farmasi/resep/copy', function () use ($pharmacyController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $pharmacyController->apiCopyResep($user, $data);
        });
        $router->get('/api/v1/farmasi/stok-batch', function () use ($pharmacyController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            return $pharmacyController->apiListStokBatch($queryParams);
        });
        $router->post('/api/v1/farmasi/stok-adjust', function () use ($pharmacyController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $pharmacyController->apiAdjustStokBatch($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });

        // Kasir, Split Billing 4-Pilar & POS Apotek Umum (PLN-22)
        $renderBilling = function () use ($billingController): array {
            $user = $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd', 'apotek_umum']);
            return $billingController->index($user, $this->csrfGuard->getToken());
        };
        $router->get('/kasir', $renderBilling);
        $router->get('/kasir-rawat-jalan', $renderBilling);

        $router->get('/api/v1/kasir/queue', function () use ($billingController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            return $billingController->apiQueue($queryParams);
        });
        $router->get('/api/v1/kasir/detail', function () use ($billingController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            return $billingController->apiDetail($queryParams);
        });
        $router->post('/api/v1/kasir/item', function () use ($billingController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $billingController->apiAddLedgerItem($data);
        });
        $router->post('/api/v1/kasir/pay', function () use ($billingController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $billingController->apiPay($data);
        });
        $router->post('/api/v1/kasir/settle-piutang', function () use ($billingController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $billingController->apiSettlePiutang($data);
        });
        $router->get('/kasir/faktur', function () use ($billingController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            return $billingController->printFaktur($queryParams);
        });
        $router->post('/api/v1/kasir/email-faktur', function () use ($billingController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $billingController->apiEmailFaktur($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/kasir/void', function () use ($billingController, $postData, $rawBody, $headers, $ipAddress): array {
            $user = $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $billingController->apiVoid($user, $data, $ipAddress);
        });
        $router->post('/api/v1/kasir/reconcile', function () use ($billingController, $postData, $rawBody, $headers, $ipAddress): array {
            $user = $this->requireAnyServiceModuleAccess(['kasir', 'kasir_rawat_jalan', 'kasir_rawat_inap', 'kasir_igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $billingController->apiReconcile($user, $data, $ipAddress);
        });
        $router->post('/api/v1/apotek-umum/pos', function () use ($billingController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['kasir', 'apotek_umum', 'farmasi']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $billingController->apiPosApotekUmum($user, $data);
        });
        $router->get('/api/v1/apotek-umum/sales', function () use ($billingController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['kasir', 'apotek_umum', 'farmasi']);
            return $billingController->apiListPosSales($queryParams);
        });
        $router->get('/apotek-umum/struk', function () use ($billingController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['kasir', 'apotek_umum', 'farmasi']);
            return $billingController->printPosReceipt($queryParams);
        });

        // Laboratorium, Radiologi & Laporan Operasional (PLN-26)
        $router->get('/laboratorium', function () use ($supportController): array {
            $user = $this->requireAnyServiceModuleAccess(['laboratorium', 'dokter_ralan', 'rawat_jalan']);
            return $supportController->index($user, $this->csrfGuard->getToken(), 'lab');
        });
        $router->get('/radiologi', function () use ($supportController): array {
            $user = $this->requireAnyServiceModuleAccess(['radiologi', 'dokter_ralan', 'rawat_jalan']);
            return $supportController->index($user, $this->csrfGuard->getToken(), 'radiologi');
        });
        $router->get('/laporan', function () use ($supportController): array {
            $user = $this->requireAnyServiceModuleAccess(['laporan', 'kasir', 'laboratorium', 'radiologi']);
            return $supportController->index($user, $this->csrfGuard->getToken(), 'laporan');
        });

        $router->get('/api/v1/penunjang/lab/orders', function () use ($supportController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['laboratorium', 'dokter_ralan', 'rawat_jalan']);
            return $supportController->listLabOrders($queryParams);
        });
        $router->post('/api/v1/penunjang/lab/orders', function () use ($supportController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['laboratorium', 'dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $supportController->createLabOrder($data);
        });
        $router->post('/api/v1/penunjang/lab/results', function () use ($supportController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['laboratorium']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $supportController->saveLabResult($data);
        });
        $router->post('/api/v1/penunjang/lab/delete', function () use ($supportController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['laboratorium', 'dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $supportController->deleteLabOrder($data);
        });

        $router->get('/api/v1/penunjang/radiologi/orders', function () use ($supportController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['radiologi', 'dokter_ralan', 'rawat_jalan']);
            return $supportController->listRadiologyOrders($queryParams);
        });
        $router->post('/api/v1/penunjang/radiologi/orders', function () use ($supportController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['radiologi', 'dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $supportController->createRadiologyOrder($data);
        });
        $router->post('/api/v1/penunjang/radiologi/results', function () use ($supportController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['radiologi']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $supportController->saveRadiologyResult($data);
        });
        $router->post('/api/v1/penunjang/radiologi/orders/delete', function () use ($supportController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['radiologi']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $supportController->deleteRadiologyOrder($data);
        });
        $router->get('/penunjang/lab/cetak', function () use ($supportController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['laboratorium', 'dokter_ralan', 'rawat_jalan']);
            return $supportController->printLabResult($queryParams);
        });
        $router->get('/api/v1/penunjang/lab/templates', function () use ($supportController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['laboratorium', 'dokter_ralan', 'igd']);
            return $supportController->apiListLabTemplates($queryParams);
        });
        $router->post('/api/v1/penunjang/lab/templates-save', function () use ($supportController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['laboratorium', 'dokter_ralan', 'igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $supportController->apiSaveLabTemplate($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/penunjang/radiologi/cetak', function () use ($supportController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['radiologi', 'dokter_ralan', 'rawat_jalan']);
            return $supportController->printRadiologyResult($queryParams);
        });
        $router->get('/api/v1/penunjang/radiologi/images', function () use ($supportController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['radiologi', 'dokter_ralan', 'rawat_jalan']);
            return $supportController->apiListRadImages($queryParams);
        });
        $router->post('/api/v1/penunjang/radiologi/images-save', function () use ($supportController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['radiologi', 'dokter_ralan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $supportController->apiSaveRadImage($this->parseJsonOrPost($rawBody, $postData, $headers));
        });

        $router->get('/api/v1/laporan/operasional', function () use ($supportController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['laporan', 'kasir', 'laboratorium', 'radiologi']);
            return $supportController->operationalReports($queryParams);
        });
        $router->get('/api/v1/laporan/export-csv', function () use ($supportController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['laporan', 'kasir', 'laboratorium', 'radiologi']);
            return $supportController->exportOperationalCsv($queryParams);
        });

        // Bridging BPJS, SatuSehat FHIR & HFIS LEAN (PLN-30)
        $router->get('/bridging', function () use ($bridgingController): array {
            $user = $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'satu_sehat', 'vedika']);
            return $bridgingController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/bridging/status', function () use ($bridgingController): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'satu_sehat', 'vedika']);
            return $bridgingController->status();
        });
        $router->get('/api/v1/bridging/lean/estimate', function () use ($bridgingController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'pendaftaran', 'anjungan']);
            return $bridgingController->leanEstimate($queryParams);
        });
        $router->post('/api/v1/bridging/satusehat/queue', function () use ($bridgingController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'satu_sehat', 'dokter_ralan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $bridgingController->queueSatuSehat($data);
        });
        $router->post('/api/v1/bridging/satusehat/process', function () use ($bridgingController, $postData, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'satu_sehat']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $bridgingController->processQueue();
        });
        $router->post('/api/v1/bridging/vedika/verify', function () use ($bridgingController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vedika', 'kasir']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $bridgingController->verifyVedika($data, $user->getUsername());
        });
        $router->get('/api/v1/bridging/vedika/claims', function () use ($bridgingController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vedika', 'kasir']);
            return $bridgingController->listVedikaClaims($queryParams);
        });
        $router->get('/vedika/bundel', function () use ($bridgingController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vedika', 'kasir']);
            return $bridgingController->printVedikaBundle($queryParams);
        });
        $router->get('/api/v1/bridging/vclaim/peserta', function () use ($vclaimController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'admin']);
            return $vclaimController->apiCariPeserta($queryParams);
        });
        $router->get('/api/v1/bridging/vclaim/sep', function () use ($vclaimController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'admin']);
            return $vclaimController->apiSepList($queryParams);
        });
        $router->post('/api/v1/bridging/vclaim/sep-save', function () use ($vclaimController, $postData, $rawBody, $headers): array {
            $actor = $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $vclaimController->apiSepSave($actor, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/bridging/vclaim/sep/cetak', function () use ($vclaimController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'admin']);
            return $vclaimController->printSep($queryParams);
        });
        $router->get('/api/v1/bridging/vclaim/rujukan', function () use ($vclaimController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'admin']);
            return $vclaimController->apiRujukanList($queryParams);
        });
        $router->post('/api/v1/bridging/vclaim/rujukan-save', function () use ($vclaimController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $vclaimController->apiRujukanSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/bridging/vclaim/surat-kontrol', function () use ($vclaimController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'admin']);
            return $vclaimController->apiSuratKontrolList($queryParams);
        });
        $router->post('/api/v1/bridging/vclaim/surat-kontrol-save', function () use ($vclaimController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'vclaim', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $vclaimController->apiSuratKontrolSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/pcare', function () use ($pcareController): array {
            $user = $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            return $pcareController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/pcare/rujukan/cetak', function () use ($pcareController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            return $pcareController->printRujukan($queryParams);
        });
        $router->get('/api/v1/pcare/pendaftaran', function () use ($pcareController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            return $pcareController->apiPendaftaranList($queryParams);
        });
        $router->post('/api/v1/pcare/pendaftaran-save', function () use ($pcareController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $pcareController->apiPendaftaranSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/pcare/kunjungan', function () use ($pcareController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            return $pcareController->apiKunjunganList($queryParams);
        });
        $router->post('/api/v1/pcare/kunjungan-save', function () use ($pcareController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $pcareController->apiKunjunganSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/pcare/rujukan', function () use ($pcareController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            return $pcareController->apiRujukanList($queryParams);
        });
        $router->post('/api/v1/pcare/rujukan-save', function () use ($pcareController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $pcareController->apiRujukanSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/pcare/obat', function () use ($pcareController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            return $pcareController->apiObatList($queryParams);
        });
        $router->post('/api/v1/pcare/obat-save', function () use ($pcareController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $pcareController->apiObatSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/pcare/tindakan', function () use ($pcareController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            return $pcareController->apiTindakanList($queryParams);
        });
        $router->post('/api/v1/pcare/tindakan-save', function () use ($pcareController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'pcare', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $pcareController->apiTindakanSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/bridging/hfis/jadwal', function () use ($hfisAntrolController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'bridging_hfis', 'admin']);
            return $hfisAntrolController->apiJadwalList($queryParams);
        });
        $router->post('/api/v1/bridging/hfis/jadwal-sync', function () use ($hfisAntrolController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'bridging_hfis', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $hfisAntrolController->apiJadwalSync($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/bridging/antrol/tasks', function () use ($hfisAntrolController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'bridging_hfis', 'admin']);
            return $hfisAntrolController->apiAntrolList($queryParams);
        });
        $router->post('/api/v1/bridging/antrol/task-push', function () use ($hfisAntrolController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'bridging_hfis', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $hfisAntrolController->apiAntrolPush($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/jkn-mobile/token', fn(): array => $jknMobileController->getToken($headers, $queryParams));
        $router->post('/api/v1/jkn-mobile/antrean', fn(): array => $jknMobileController->ambilAntrean($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/status-antrean', fn(): array => $jknMobileController->statusAntrean($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/sisa-antrean', fn(): array => $jknMobileController->sisaAntrean($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/batal-antrean', fn(): array => $jknMobileController->batalAntrean($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/checkin', fn(): array => $jknMobileController->checkIn($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/pasien-baru', fn(): array => $jknMobileController->pasienBaru($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/antrean-farmasi', fn(): array => $jknMobileController->ambilAntreanFarmasi($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/status-antrean-farmasi', fn(): array => $jknMobileController->statusAntreanFarmasi($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/jadwal-operasi-rs', fn(): array => $jknMobileController->jadwalOperasiRs($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile/jadwal-operasi-pasien', fn(): array => $jknMobileController->jadwalOperasiPasien($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->get('/api/v1/jkn-mobile-fktp/auth', fn(): array => $jknMobileFktpController->auth($headers, $queryParams));
        $router->post('/api/v1/jkn-mobile-fktp/antrean', fn(): array => $jknMobileFktpController->antrean($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile-fktp/status-antrean', fn(): array => $jknMobileFktpController->statusAntrean($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile-fktp/sisa-antrean', fn(): array => $jknMobileFktpController->sisaAntrean($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile-fktp/batal-antrean', fn(): array => $jknMobileFktpController->batalAntrean($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->post('/api/v1/jkn-mobile-fktp/peserta', fn(): array => $jknMobileFktpController->peserta($headers, $this->parseJsonOrPost($rawBody, $postData, $headers)));
        $router->get('/api/v1/bridging/satusehat/mappings', function () use ($satuSehatController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'satu_sehat', 'admin']);
            return $satuSehatController->apiMappingList($queryParams);
        });
        $router->post('/api/v1/bridging/satusehat/mappings-save', function () use ($satuSehatController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'satu_sehat', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $satuSehatController->apiMappingSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/bridging/satusehat/fhir-enqueue', function () use ($satuSehatController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['bridging_bpjs', 'satu_sehat', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $satuSehatController->apiFhirEnqueue($this->parseJsonOrPost($rawBody, $postData, $headers));
        });

        // WhatsApp Gateway CRM & Home Care Preceptorship (PLN-33)
        $router->get('/wagateway', function () use ($communicationController): array {
            $user = $this->requireAnyServiceModuleAccess(['wagateway', 'crm', 'admin']);
            return $communicationController->indexWa($user, $this->csrfGuard->getToken());
        });
        $router->get('/homecare', function () use ($communicationController): array {
            $user = $this->requireAnyServiceModuleAccess(['home_care', 'homecare', 'dokter_ralan', 'rawat_jalan']);
            return $communicationController->indexHomeCare($user, $this->csrfGuard->getToken());
        });

        $router->post('/api/v1/wagateway/send', function () use ($communicationController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['wagateway', 'crm', 'admin', 'pendaftaran', 'farmasi', 'kasir']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $communicationController->apiSendWa($user, $data);
        });
        $router->post('/api/v1/wagateway/webhook', function () use ($communicationController, $settingService, $postData, $rawBody, $headers): array {
            $expectedSecret = trim((string) $settingService->get('wagateway', 'webhook_secret', ''));
            $providedToken = '';
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, 'X-Webhook-Token') === 0) {
                    $providedToken = trim($v);
                    break;
                }
            }
            if ($expectedSecret === '' || $providedToken === '' || !hash_equals($expectedSecret, $providedToken)) {
                throw new ForbiddenException('Webhook signature/token tidak valid atau belum dikonfigurasi.');
            }
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $communicationController->apiInboundWebhook($data);
        });
        $router->get('/api/v1/wagateway/outbox', function () use ($communicationController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['wagateway', 'crm', 'admin']);
            return $communicationController->apiListWaOutbox($queryParams);
        });
        $router->get('/api/v1/wagateway/whitelist', function () use ($communicationController): array {
            $this->requireAnyServiceModuleAccess(['wagateway', 'crm', 'admin']);
            return $communicationController->apiListWaWhitelist();
        });
        $router->post('/api/v1/wagateway/whitelist', function () use ($communicationController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['wagateway', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $communicationController->apiAddWaWhitelist($data);
        });

        $router->post('/api/v1/homecare/kunjungan', function () use ($communicationController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['home_care', 'homecare', 'dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $communicationController->apiCreateHomeCare($data);
        });
        $router->post('/api/v1/homecare/preceptor/assign', function () use ($communicationController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['home_care', 'homecare']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $communicationController->apiAssignPreceptor($data);
        });
        $router->get('/api/v1/homecare/supervisor/visits', function () use ($communicationController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['home_care', 'homecare', 'admin']);
            return $communicationController->apiSupervisorVisits($queryParams);
        });

        // Audit ACID, Integritas Relasi & Rotasi Log (PLN-36)
        $router->get('/audit', function () use ($auditController): array {
            $user = $this->requireAnyServiceModuleAccess(['audit', 'admin']);
            return $auditController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/audit/integrity/orphans', function () use ($auditController): array {
            $this->requireAnyServiceModuleAccess(['audit', 'admin']);
            return $auditController->apiOrphanCheck();
        });
        $router->get('/api/v1/audit/integrity/billing-balance', function () use ($auditController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['audit', 'admin', 'kasir']);
            return $auditController->apiBillingBalance($queryParams);
        });
        $router->get('/api/v1/audit/integrity/stock-balance', function () use ($auditController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['audit', 'admin', 'farmasi']);
            return $auditController->apiStockBalance($queryParams);
        });
        $router->post('/api/v1/audit/logs/rotate', function () use ($auditController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['audit', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $auditController->apiRotateLogs($data);
        });
        $router->get('/api/v1/audit/logs', function () use ($auditController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['audit', 'admin']);
            return $auditController->apiListLogs($queryParams);
        });

        // Master Pasien (GAP No. 1)
        $router->get('/pasien', function () use ($pasienController): array {
            $user = $this->requireAnyServiceModuleAccess(['pasien', 'pendaftaran']);
            return $pasienController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/pasien/kartu', function () use ($pasienController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['pasien', 'pendaftaran']);
            return $pasienController->cetakKartu($queryParams);
        });
        $router->get('/api/v1/pasien/list', function () use ($pasienController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['pasien', 'pendaftaran']);
            return $pasienController->apiList($queryParams);
        });
        $router->get('/api/v1/pasien/max-id', function () use ($pasienController): array {
            $this->requireAnyServiceModuleAccess(['pasien', 'pendaftaran']);
            return $pasienController->apiMaxId();
        });
        $router->get('/api/v1/pasien/detail', function () use ($pasienController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['pasien', 'pendaftaran']);
            return $pasienController->apiDetail($queryParams);
        });
        $router->get('/api/v1/pasien/history', function () use ($pasienController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['pasien', 'pendaftaran', 'dokter_ralan']);
            return $pasienController->apiHistory($queryParams);
        });
        $router->post('/api/v1/pasien/save', function () use ($pasienController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['pasien', 'pendaftaran']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $pasienController->apiSave($data);
        });
        $router->post('/api/v1/pasien/delete', function () use ($pasienController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['pasien', 'pendaftaran']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $pasienController->apiDelete($data);
        });

        $router->get('/master', function () use ($masterController): array {
            $user = $this->requireAnyServiceModuleAccess(['master', 'admin']);
            return $masterController->index($user, $this->csrfGuard->getToken());
        });
        foreach (['poliklinik', 'dokter', 'penjab', 'jns_perawatan', 'jadwal', 'bangsal', 'kamar', 'penyakit'] as $entity) {
            $router->get("/api/v1/master/{$entity}", function () use ($masterController, $entity, $queryParams): array {
                $this->requireAnyServiceModuleAccess(['master', 'admin']);
                return $masterController->apiList($entity, $queryParams);
            });
            $router->post("/api/v1/master/{$entity}/save", function () use ($masterController, $entity, $postData, $rawBody, $headers): array {
                $this->requireAnyServiceModuleAccess(['master', 'admin']);
                $this->assertCsrfHeaderOrPost($postData, $headers);
                $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
                return $masterController->apiSave($entity, $data);
            });
            $router->post("/api/v1/master/{$entity}/delete", function () use ($masterController, $entity, $postData, $rawBody, $headers): array {
                $this->requireAnyServiceModuleAccess(['master', 'admin']);
                $this->assertCsrfHeaderOrPost($postData, $headers);
                $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
                return $masterController->apiDelete($entity, $data);
            });
        }

        $router->get('/surat', function () use ($suratController): array {
            $user = $this->requireAnyServiceModuleAccess(['surat', 'dokter_ralan', 'rawat_jalan']);
            return $suratController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/surat/cetak', function () use ($suratController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['surat', 'dokter_ralan', 'rawat_jalan']);
            return $suratController->printSurat($queryParams);
        });
        $router->get('/api/v1/surat/list', function () use ($suratController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['surat', 'dokter_ralan', 'rawat_jalan']);
            return $suratController->apiList($queryParams);
        });
        $router->post('/api/v1/surat/create', function () use ($suratController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['surat', 'dokter_ralan', 'rawat_jalan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $suratController->apiCreate($user, $data);
        });
        $router->get('/api/v1/rujukan-internal', function () use ($suratController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['surat', 'dokter_ralan', 'rawat_jalan', 'sep_internal']);
            return $suratController->apiListRujukanInternal($queryParams);
        });
        $router->post('/api/v1/rujukan-internal', function () use ($suratController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['surat', 'dokter_ralan', 'rawat_jalan', 'sep_internal']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $suratController->apiCreateRujukanInternal($data);
        });

        $router->get('/kepegawaian', function () use ($kepegawaianController): array {
            $user = $this->requireAnyServiceModuleAccess(['kepegawaian', 'admin']);
            return $kepegawaianController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/kepegawaian/cetak', function () use ($kepegawaianController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['kepegawaian', 'admin']);
            return $kepegawaianController->printDirectory($queryParams);
        });
        foreach (['pegawai', 'petugas', 'sdm_berkas'] as $sdmEntity) {
            $router->get("/api/v1/kepegawaian/{$sdmEntity}", function () use ($kepegawaianController, $sdmEntity, $queryParams): array {
                $this->requireAnyServiceModuleAccess(['kepegawaian', 'admin']);
                return $kepegawaianController->apiList($sdmEntity, $queryParams);
            });
            $router->post("/api/v1/kepegawaian/{$sdmEntity}/save", function () use ($kepegawaianController, $sdmEntity, $postData, $rawBody, $headers): array {
                $this->requireAnyServiceModuleAccess(['kepegawaian', 'admin']);
                $this->assertCsrfHeaderOrPost($postData, $headers);
                $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
                return $kepegawaianController->apiSave($sdmEntity, $data);
            });
            $router->post("/api/v1/kepegawaian/{$sdmEntity}/delete", function () use ($kepegawaianController, $sdmEntity, $postData, $rawBody, $headers): array {
                $this->requireAnyServiceModuleAccess(['kepegawaian', 'admin']);
                $this->assertCsrfHeaderOrPost($postData, $headers);
                $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
                return $kepegawaianController->apiDelete($sdmEntity, $data);
            });
        }

        $router->get('/frontoffice', function () use ($frontOfficeController): array {
            $user = $this->requireAnyServiceModuleAccess(['frontoffice', 'pendaftaran', 'rawat_jalan']);
            return $frontOfficeController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/frontoffice/overview', function () use ($frontOfficeController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['frontoffice', 'pendaftaran', 'rawat_jalan']);
            return $frontOfficeController->apiOverview($queryParams);
        });

        $router->get('/igd', function () use ($igdController): array {
            $user = $this->requireAnyServiceModuleAccess(['igd', 'dokter_igd', 'pendaftaran']);
            return $igdController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/igd/list', function () use ($igdController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['igd', 'dokter_igd', 'pendaftaran']);
            return $igdController->apiList($queryParams);
        });
        $router->post('/api/v1/igd/triase', function () use ($igdController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['igd', 'dokter_igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $igdController->apiSaveTriase($data);
        });
        $router->post('/api/v1/igd/disposisi', function () use ($igdController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['igd', 'dokter_igd']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $igdController->apiUpdateDisposisi($data);
        });

        $router->get('/rawat-inap', function () use ($ranapController): array {
            $user = $this->requireAnyServiceModuleAccess(['rawat_inap', 'dokter_ranap', 'kasir_rawat_inap']);
            return $ranapController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/ranap/inpatients', function () use ($ranapController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['rawat_inap', 'dokter_ranap', 'kasir_rawat_inap']);
            return $ranapController->apiListInpatients($queryParams);
        });
        $router->get('/api/v1/ranap/kamar', function () use ($ranapController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['rawat_inap', 'dokter_ranap', 'master']);
            return $ranapController->apiListKamar($queryParams);
        });
        $router->post('/api/v1/ranap/kamar/save', function () use ($ranapController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['rawat_inap', 'master']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $ranapController->apiSaveKamar($data);
        });
        $router->post('/api/v1/ranap/checkin', function () use ($ranapController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['rawat_inap', 'pendaftaran']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $ranapController->apiCheckIn($data);
        });
        $router->post('/api/v1/ranap/checkout', function () use ($ranapController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['rawat_inap', 'kasir_rawat_inap']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $ranapController->apiCheckOut($data);
        });

        $router->get('/dokter-ranap', function () use ($dokterRanapController): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            return $dokterRanapController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/dokter-ranap/patients', function () use ($dokterRanapController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            return $dokterRanapController->apiPatients($queryParams);
        });
        $router->get('/api/v1/dokter-ranap/cppt', function () use ($dokterRanapController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            return $dokterRanapController->apiListCppt($queryParams);
        });
        $router->post('/api/v1/dokter-ranap/cppt/save', function () use ($dokterRanapController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $dokterRanapController->apiSaveCppt($user, $data);
        });
        $router->get('/api/v1/dokter-ranap/obat/search', function () use ($pharmacyController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            return $pharmacyController->apiSearchObat($queryParams);
        });
        $router->post('/api/v1/dokter-ranap/eresep/create', function () use ($pharmacyController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            $data['status'] = 'ranap';
            return $pharmacyController->apiCreateResep($user, $data);
        });
        $router->get('/api/v1/dokter-ranap/resume', function () use ($tindakanRepo, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            $nr = trim((string) ($queryParams['no_rawat'] ?? ''));
            $data = $nr !== '' ? $tindakanRepo->findResume(new \App\Domain\Registration\ValueObject\NoRawat($nr)) : null;
            return ['status' => 200, 'headers' => ['Content-Type' => 'application/json; charset=utf-8'], 'body' => json_encode(['status' => 'success', 'data' => $data], JSON_THROW_ON_ERROR)];
        });
        $router->post('/api/v1/dokter-ranap/resume/save', function () use ($tindakanRepo, $tx, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            $data['kd_dokter'] = $user->getUsername();
            (new SaveResumeMedisUseCase($tindakanRepo, $tx))->execute($data);
            return ['status' => 200, 'headers' => ['Content-Type' => 'application/json; charset=utf-8'], 'body' => json_encode(['status' => 'success', 'message' => 'Resume pulang ranap tersimpan.'], JSON_THROW_ON_ERROR)];
        });
        $router->get('/dokter-ranap/resume/cetak', function () use ($dokterRalanController, $queryParams): array {
            $user = $this->requireAnyServiceModuleAccess(['dokter_ranap', 'rawat_inap']);
            return $dokterRalanController->printResumeMedis($user, $queryParams);
        });

        $router->get('/farmasi-gudang', function () use ($gudangFarmasiController): array {
            $user = $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'apotek_umum']);
            return $gudangFarmasiController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/gudang/barang', function () use ($gudangFarmasiController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'apotek_umum']);
            return $gudangFarmasiController->apiListBarang($queryParams);
        });
        $router->post('/api/v1/gudang/barang/save', function () use ($gudangFarmasiController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $gudangFarmasiController->apiSaveBarang($data);
        });
        $router->post('/api/v1/gudang/receive', function () use ($gudangFarmasiController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $gudangFarmasiController->apiReceiveStock($user, $data);
        });
        $router->post('/api/v1/gudang/mutate', function () use ($gudangFarmasiController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $gudangFarmasiController->apiMutateStock($user, $data);
        });
        $router->get('/api/v1/gudang/kartu-stok', function () use ($gudangFarmasiController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['farmasi', 'apotek_ralan', 'apotek_umum']);
            return $gudangFarmasiController->apiKartuStok($queryParams);
        });

        $router->get('/keuangan', function () use ($keuanganController): array {
            $user = $this->requireAnyServiceModuleAccess(['keuangan', 'kasir', 'admin']);
            return $keuanganController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/keuangan/overview', function () use ($keuanganController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['keuangan', 'kasir', 'admin']);
            return $keuanganController->apiOverview($queryParams);
        });
        $router->post('/api/v1/keuangan/jurnal', function () use ($keuanganController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['keuangan', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $keuanganController->apiPostJurnal($data);
        });
        $router->post('/api/v1/keuangan/rekening', function () use ($keuanganController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['keuangan', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $keuanganController->apiSaveRekening($data);
        });

        $router->get('/jasa-medis', function () use ($jasaMedisController): array {
            $user = $this->requireAnyServiceModuleAccess(['jasa_medis', 'keuangan', 'admin']);
            return $jasaMedisController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/jasa-medis/cetak', function () use ($jasaMedisController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['jasa_medis', 'keuangan', 'admin']);
            return $jasaMedisController->printRekap($queryParams);
        });
        $router->get('/api/v1/jasa-medis/summary', function () use ($jasaMedisController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['jasa_medis', 'keuangan', 'admin']);
            return $jasaMedisController->apiSummary($queryParams);
        });

        $router->get('/manajemen', function () use ($manajemenController): array {
            $user = $this->requireAnyServiceModuleAccess(['manajemen', 'laporan', 'admin']);
            return $manajemenController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/manajemen/dashboard', function () use ($manajemenController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['manajemen', 'laporan', 'admin']);
            return $manajemenController->apiDashboard($queryParams);
        });

        $router->get('/profil', function () use ($profilController): array {
            $user = $this->requireAuthenticatedUser();
            return $profilController->index($user, $this->csrfGuard->getToken());
        });
        $router->post('/api/v1/profil/update', function () use ($profilController, $postData, $rawBody, $headers): array {
            $user = $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $profilController->apiUpdateProfile($user, $data);
        });
        $router->post('/api/v1/profil/password', function () use ($profilController, $postData, $rawBody, $headers): array {
            $user = $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $profilController->apiChangePassword($user, $data);
        });

        $router->get('/presensi', function () use ($presensiController): array {
            $user = $this->requireAuthenticatedUser();
            return $presensiController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/presensi/list', function () use ($presensiController, $queryParams): array {
            $this->requireAuthenticatedUser();
            return $presensiController->apiList($queryParams);
        });
        $router->post('/api/v1/presensi/clock-in', function () use ($presensiController, $postData, $rawBody, $headers): array {
            $user = $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $presensiController->apiClockIn($user, $data);
        });
        $router->post('/api/v1/presensi/clock-out', function () use ($presensiController, $postData, $rawBody, $headers): array {
            $user = $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $presensiController->apiClockOut($user, $data);
        });

        $router->get('/inventaris', function () use ($inventarisController): array {
            $user = $this->requireAnyServiceModuleAccess(['inventaris', 'admin']);
            return $inventarisController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/inventaris/list', function () use ($inventarisController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['inventaris', 'admin']);
            return $inventarisController->apiList($queryParams);
        });
        $router->post('/api/v1/inventaris/save', function () use ($inventarisController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['inventaris', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $inventarisController->apiSave($data);
        });
        $router->post('/api/v1/inventaris/delete', function () use ($inventarisController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['inventaris', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $inventarisController->apiDelete($data);
        });

        $router->get('/api-keys', function () use ($apiKeyController): array {
            $user = $this->requireAnyServiceModuleAccess(['mlite_api_key', 'admin']);
            return $apiKeyController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/api-keys', function () use ($apiKeyController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['mlite_api_key', 'admin']);
            return $apiKeyController->apiList($queryParams);
        });
        $router->post('/api/v1/api-keys/create', function () use ($apiKeyController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['mlite_api_key', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $apiKeyController->apiCreate($data);
        });
        $router->post('/api/v1/api-keys/toggle', function () use ($apiKeyController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['mlite_api_key', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $apiKeyController->apiToggle($data);
        });
        $router->post('/api/v1/api-keys/delete', function () use ($apiKeyController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['mlite_api_key', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $apiKeyController->apiDelete($data);
        });

        $router->get('/modules', function () use ($moduleManagerController): array {
            $user = $this->requireAnyServiceModuleAccess(['modules', 'admin']);
            return $moduleManagerController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/modules', function () use ($moduleManagerController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['modules', 'admin']);
            return $moduleManagerController->apiList($queryParams);
        });
        $router->post('/api/v1/modules/toggle', function () use ($moduleManagerController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['modules', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $moduleManagerController->apiToggle($data);
        });
        $router->post('/api/v1/modules/sequence', function () use ($moduleManagerController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['modules', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $moduleManagerController->apiSequence($data);
        });

        $router->get('/verifikasi', function () use ($verifikasiController, $queryParams): array {
            return $verifikasiController->index($queryParams);
        });
        $router->get('/api/v1/verifikasi', function () use ($verifikasiController, $queryParams): array {
            return $verifikasiController->apiVerify($queryParams);
        });

        $router->get('/penjualan', function () use ($penjualanController): array {
            $user = $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            return $penjualanController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/penjualan/faktur', function () use ($penjualanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            return $penjualanController->printFaktur($queryParams);
        });
        $router->get('/api/v1/penjualan/barang', function () use ($penjualanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            return $penjualanController->apiBarangList($queryParams);
        });
        $router->post('/api/v1/penjualan/barang-save', function () use ($penjualanController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $penjualanController->apiBarangSave($data);
        });
        $router->get('/api/v1/penjualan/orders', function () use ($penjualanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            return $penjualanController->apiOrders($queryParams);
        });
        $router->get('/api/v1/penjualan/order-detail', function () use ($penjualanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            return $penjualanController->apiOrderDetail($queryParams);
        });
        $router->post('/api/v1/penjualan/order-create', function () use ($penjualanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $penjualanController->apiOrderCreate($user, $data);
        });
        $router->post('/api/v1/penjualan/item-add', function () use ($penjualanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $penjualanController->apiAddItem($user, $data);
        });
        $router->post('/api/v1/penjualan/item-remove', function () use ($penjualanController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $penjualanController->apiRemoveItem($data);
        });
        $router->post('/api/v1/penjualan/settle', function () use ($penjualanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $penjualanController->apiSettle($user, $data);
        });
        $router->get('/api/v1/penjualan/report', function () use ($penjualanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['penjualan', 'apotek_umum', 'kasir', 'admin']);
            return $penjualanController->apiReport($queryParams);
        });

        $router->get('/operasi', function () use ($operasiController): array {
            $user = $this->requireAnyServiceModuleAccess(['operasi', 'rawat_inap', 'dokter_ranap', 'admin']);
            return $operasiController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/operasi/paket', function () use ($operasiController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['operasi', 'rawat_inap', 'dokter_ranap', 'admin']);
            return $operasiController->apiPaketList($queryParams);
        });
        $router->post('/api/v1/operasi/paket-save', function () use ($operasiController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['operasi', 'rawat_inap', 'dokter_ranap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $operasiController->apiPaketSave($data);
        });
        $router->get('/api/v1/operasi/bookings', function () use ($operasiController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['operasi', 'rawat_inap', 'dokter_ranap', 'admin']);
            return $operasiController->apiBookingList($queryParams);
        });
        $router->post('/api/v1/operasi/booking-save', function () use ($operasiController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['operasi', 'rawat_inap', 'dokter_ranap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $operasiController->apiBookingSave($data);
        });
        $router->post('/api/v1/operasi/booking-status', function () use ($operasiController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['operasi', 'rawat_inap', 'dokter_ranap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $operasiController->apiBookingStatus($data);
        });
        $router->get('/api/v1/operasi/laporan', function () use ($operasiController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['operasi', 'rawat_inap', 'dokter_ranap', 'admin']);
            return $operasiController->apiLaporanList($queryParams);
        });
        $router->post('/api/v1/operasi/laporan-save', function () use ($operasiController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['operasi', 'rawat_inap', 'dokter_ranap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $operasiController->apiLaporanSave($data);
        });

        $router->get('/ruang-tindakan', function () use ($ruangTindakanController): array {
            $user = $this->requireAnyServiceModuleAccess(['ruang_tindakan', 'rawat_jalan', 'igd', 'admin']);
            return $ruangTindakanController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/ruang-tindakan/cetak', function () use ($ruangTindakanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['ruang_tindakan', 'rawat_jalan', 'igd', 'admin']);
            return $ruangTindakanController->printLaporan($queryParams);
        });
        $router->get('/api/v1/ruang-tindakan/list', function () use ($ruangTindakanController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['ruang_tindakan', 'rawat_jalan', 'igd', 'admin']);
            return $ruangTindakanController->apiList($queryParams);
        });
        $router->post('/api/v1/ruang-tindakan/save', function () use ($ruangTindakanController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['ruang_tindakan', 'rawat_jalan', 'igd', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $ruangTindakanController->apiSave($user, $data);
        });
        $router->post('/api/v1/ruang-tindakan/delete', function () use ($ruangTindakanController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['ruang_tindakan', 'rawat_jalan', 'igd', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            $data = $this->parseJsonOrPost($rawBody, $postData, $headers);
            return $ruangTindakanController->apiDelete($data);
        });

        $router->get('/utd', function () use ($utdController): array {
            $user = $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            return $utdController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/utd/komponen', function () use ($utdController): array {
            $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            return $utdController->apiKomponenList();
        });
        $router->post('/api/v1/utd/komponen-save', function () use ($utdController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $utdController->apiKomponenSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/utd/pendonor', function () use ($utdController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            return $utdController->apiPendonorList($queryParams);
        });
        $router->post('/api/v1/utd/pendonor-save', function () use ($utdController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $utdController->apiPendonorSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/utd/stok', function () use ($utdController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            return $utdController->apiStokList($queryParams);
        });
        $router->post('/api/v1/utd/stok-save', function () use ($utdController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $utdController->apiStokSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/utd/permintaan', function () use ($utdController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            return $utdController->apiPermintaanList($queryParams);
        });
        $router->post('/api/v1/utd/permintaan-save', function () use ($utdController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $utdController->apiPermintaanSave($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/utd/crossmatch', function () use ($utdController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['utd', 'laboratorium', 'rawat_inap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $utdController->apiCrossmatch($this->parseJsonOrPost($rawBody, $postData, $headers));
        });

        $router->get('/veronisa', function () use ($veronisaController): array {
            $user = $this->requireAnyServiceModuleAccess(['veronisa', 'vedika', 'bridging_bpjs', 'admin']);
            return $veronisaController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/veronisa/pdf', function () use ($veronisaController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['veronisa', 'vedika', 'bridging_bpjs', 'admin']);
            return $veronisaController->printBerkas($queryParams);
        });
        $router->get('/api/v1/veronisa/claims', function () use ($veronisaController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['veronisa', 'vedika', 'bridging_bpjs', 'admin']);
            return $veronisaController->apiClaimsList($queryParams);
        });
        $router->post('/api/v1/veronisa/claims-save', function () use ($veronisaController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['veronisa', 'vedika', 'bridging_bpjs', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $veronisaController->apiClaimSave($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/veronisa/claims-status', function () use ($veronisaController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['veronisa', 'vedika', 'bridging_bpjs', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $veronisaController->apiClaimStatus($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/veronisa/claims-delete', function () use ($veronisaController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['veronisa', 'vedika', 'bridging_bpjs', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $veronisaController->apiClaimDelete($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/veronisa/mapping', function () use ($veronisaController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['veronisa', 'vedika', 'bridging_bpjs', 'admin']);
            return $veronisaController->apiMappingList($queryParams);
        });
        $router->post('/api/v1/veronisa/mapping-save', function () use ($veronisaController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['veronisa', 'vedika', 'bridging_bpjs', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $veronisaController->apiMappingSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });

        $router->get('/chat', function () use ($chatController): array {
            $user = $this->requireAuthenticatedUser();
            return $chatController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/chat/conversations', function () use ($chatController): array {
            $this->requireAuthenticatedUser();
            return $chatController->apiConversations();
        });
        $router->post('/api/v1/chat/conversations-create', function () use ($chatController, $postData, $rawBody, $headers): array {
            $user = $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $chatController->apiConversationCreate($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/chat/messages', function () use ($chatController, $queryParams): array {
            $this->requireAuthenticatedUser();
            return $chatController->apiMessages($queryParams);
        });
        $router->post('/api/v1/chat/send', function () use ($chatController, $postData, $rawBody, $headers): array {
            $user = $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $chatController->apiSend($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/chat/quick-responses', function () use ($chatController): array {
            $this->requireAuthenticatedUser();
            return $chatController->apiQuickResponses();
        });
        $router->post('/api/v1/chat/quick-responses-save', function () use ($chatController, $postData, $rawBody, $headers): array {
            $user = $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $chatController->apiQuickResponseSave($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/chat/quick-responses-delete', function () use ($chatController, $postData, $rawBody, $headers): array {
            $this->requireAuthenticatedUser();
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $chatController->apiQuickResponseDelete($this->parseJsonOrPost($rawBody, $postData, $headers));
        });

        $router->get('/orthanc', function () use ($orthancController): array {
            $user = $this->requireAnyServiceModuleAccess(['orthanc', 'radiologi', 'dokter_ralan', 'admin']);
            return $orthancController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/orthanc/studies', function () use ($orthancController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['orthanc', 'radiologi', 'dokter_ralan', 'admin']);
            return $orthancController->apiStudies($queryParams);
        });
        $router->post('/api/v1/orthanc/studies-save', function () use ($orthancController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['orthanc', 'radiologi', 'dokter_ralan', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $orthancController->apiSaveStudy($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/orthanc/studies-interpretasi', function () use ($orthancController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['orthanc', 'radiologi', 'dokter_ralan', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $orthancController->apiUpdateHasilBaca($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/orthanc/studies-delete', function () use ($orthancController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['orthanc', 'radiologi', 'dokter_ralan', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $orthancController->apiDeleteStudy($this->parseJsonOrPost($rawBody, $postData, $headers));
        });

        $router->get('/smartagent', function () use ($smartAgentController): array {
            $user = $this->requireAnyServiceModuleAccess(['smartagent', 'dokter_ralan', 'dokter_ranap', 'admin']);
            return $smartAgentController->index($user, $this->csrfGuard->getToken());
        });
        $router->get('/api/v1/smartagent/knowledge', function () use ($smartAgentController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['smartagent', 'dokter_ralan', 'dokter_ranap', 'admin']);
            return $smartAgentController->apiKnowledgeList($queryParams);
        });
        $router->post('/api/v1/smartagent/knowledge-save', function () use ($smartAgentController, $postData, $rawBody, $headers): array {
            $user = $this->requireAnyServiceModuleAccess(['smartagent', 'dokter_ralan', 'dokter_ranap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $smartAgentController->apiKnowledgeSave($user, $this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/smartagent/knowledge-delete', function () use ($smartAgentController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['smartagent', 'dokter_ralan', 'dokter_ranap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $smartAgentController->apiKnowledgeDelete($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->get('/api/v1/smartagent/profiles', function () use ($smartAgentController): array {
            $this->requireAnyServiceModuleAccess(['smartagent', 'dokter_ralan', 'dokter_ranap', 'admin']);
            return $smartAgentController->apiProfiles();
        });
        $router->post('/api/v1/smartagent/profiles-save', function () use ($smartAgentController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['smartagent', 'dokter_ralan', 'dokter_ranap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $smartAgentController->apiProfileSave($this->parseJsonOrPost($rawBody, $postData, $headers));
        });
        $router->post('/api/v1/smartagent/recommend', function () use ($smartAgentController, $postData, $rawBody, $headers): array {
            $this->requireAnyServiceModuleAccess(['smartagent', 'dokter_ralan', 'dokter_ranap', 'admin']);
            $this->assertCsrfHeaderOrPost($postData, $headers);
            return $smartAgentController->apiRecommend($this->parseJsonOrPost($rawBody, $postData, $headers));
        });

        $router->get('/icare/riwayat', function () use ($icareController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['icare', 'dokter_ralan', 'dokter_ranap', 'dokter_igd', 'admin']);
            return $icareController->viewer($queryParams);
        });
        $router->get('/api/v1/icare/riwayat', function () use ($icareController, $queryParams): array {
            $this->requireAnyServiceModuleAccess(['icare', 'dokter_ralan', 'dokter_ranap', 'dokter_igd', 'admin']);
            return $icareController->apiRiwayat($queryParams);
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
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'self'; object-src 'none';",
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ]);

        return $response;
    }
}
