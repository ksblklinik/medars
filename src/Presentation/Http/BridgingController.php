<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Bridging\UseCase\ProcessBridgingQueueUseCase;
use App\Application\Bridging\UseCase\QueueSatuSehatSyncUseCase;
use App\Application\Bridging\UseCase\VerifyVedikaClaimUseCase;
use App\Domain\Bridging\Repository\BridgingQueueRepositoryInterface;
use App\Domain\Bridging\Service\BpjsGatewayService;
use App\Domain\Bridging\Service\HfisLeanCalculator;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class BridgingController
{
    public function __construct(
        private BridgingQueueRepositoryInterface $queueRepo,
        private BpjsGatewayService $bpjsGateway,
        private HfisLeanCalculator $leanCalculator,
        private QueueSatuSehatSyncUseCase $queueSatuSehatUseCase,
        private ProcessBridgingQueueUseCase $processQueueUseCase,
        private VerifyVedikaClaimUseCase $verifyVedikaUseCase,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {}

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('bridging/index', [
            'judul' => 'Bridging BPJS, SatuSehat FHIR & HFIS LEAN',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'bridging',
            'module_icon' => 'fa-network-wired',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Admin Bridging'),
            'user_role' => $currentUser?->getRole() ?? 'admin',
            'csrf_token' => $csrfToken,
            'navigations' => $navigations,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function status(): array
    {
        $bpjsProbe = $this->bpjsGateway->get('/referensi/poli/ANA');
        return $this->json([
            'ok' => true,
            'bpjs_circuit' => $bpjsProbe,
            'queue' => $this->queueRepo->listRecent(30),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function leanEstimate(array $query): array
    {
        $shift = trim((string) ($query['shift'] ?? 'Pagi')) ?: 'Pagi';
        $kdPoli = trim((string) ($query['kd_poli'] ?? 'UMU01'));
        $nmPoli = trim((string) ($query['nm_poli'] ?? 'Poli Umum'));
        $noReg = (int) ($query['no_reg'] ?? 1);
        $duration = (int) ($query['duration_minutes'] ?? 5);
        $date = trim((string) ($query['date'] ?? date('Y-m-d'))) ?: date('Y-m-d');

        return $this->json([
            'ok' => true,
            'estimate' => $this->leanCalculator->calculateEstimate($shift, $kdPoli, $nmPoli, $noReg, $duration, $date),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function queueSatuSehat(array $payload): array
    {
        try {
            $noRawat = new NoRawat((string) ($payload['no_rawat'] ?? ''));
            $queued = $this->queueSatuSehatUseCase->execute(
                noRawat: $noRawat,
                patientIhsId: trim((string) ($payload['patient_ihs_id'] ?? '')),
                practitionerIhsId: trim((string) ($payload['practitioner_ihs_id'] ?? '')),
                icd10Code: trim((string) ($payload['icd10_code'] ?? '')),
                icd10Display: trim((string) ($payload['icd10_display'] ?? '')),
            );

            return $this->json(['ok' => true, 'queued' => $queued], 201);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function processQueue(): array
    {
        return $this->json([
            'ok' => true,
            'summary' => $this->processQueueUseCase->processPending(20),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function verifyVedika(array $payload, string $verifiedBy): array
    {
        try {
            $noRawat = new NoRawat((string) ($payload['no_rawat'] ?? ''));
            $noSep = trim((string) ($payload['no_sep'] ?? ''));
            $jenisPelayanan = trim((string) ($payload['jenis_pelayanan'] ?? 'Ralan')) ?: 'Ralan';

            $verification = $this->verifyVedikaUseCase->execute($noRawat, $noSep, $verifiedBy, $jenisPelayanan);
            return $this->json(['ok' => true, 'verification' => $verification]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function json(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => (string) json_encode($data, JSON_UNESCAPED_UNICODE),
        ];
    }
}
