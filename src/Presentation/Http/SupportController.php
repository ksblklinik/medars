<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Support\UseCase\CreateLabOrderUseCase;
use App\Application\Support\UseCase\CreateRadiologyOrderUseCase;
use App\Application\Support\UseCase\DeleteLabOrderUseCase;
use App\Application\Support\UseCase\SaveLabResultUseCase;
use App\Application\Support\UseCase\SaveRadiologyResultUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Domain\Support\Repository\OperationalReportRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class SupportController
{
    public function __construct(
        private LabRadiologyRepositoryInterface $labRepo,
        private OperationalReportRepositoryInterface $reportRepo,
        private CreateLabOrderUseCase $createLabUseCase,
        private SaveLabResultUseCase $saveLabResultUseCase,
        private DeleteLabOrderUseCase $deleteLabUseCase,
        private CreateRadiologyOrderUseCase $createRadiologyUseCase,
        private SaveRadiologyResultUseCase $saveRadiologyResultUseCase,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {}

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(?UserAccount $currentUser = null, string $csrfToken = '', string $activeTab = 'lab'): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $title = match ($activeTab) {
            'radiologi' => 'Radiologi & Expertise',
            'laporan' => 'Laporan Operasional SIMRS',
            default => 'Laboratorium Klinis',
        };

        $body = $this->renderer->render('support/index', [
            'judul' => $title,
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => $activeTab === 'laporan' ? 'laporan' : ($activeTab === 'radiologi' ? 'radiologi' : 'laboratorium'),
            'module_icon' => $activeTab === 'laporan' ? 'fa-chart-bar' : ($activeTab === 'radiologi' ? 'fa-x-ray' : 'fa-flask'),
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Petugas Penunjang'),
            'user_role' => $currentUser?->getRole() ?? 'lab',
            'csrf_token' => $csrfToken,
            'navigations' => $navigations,
            'active_tab' => $activeTab,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function listLabOrders(array $query): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today));
        $to = trim((string) ($query['to'] ?? $today));

        $rows = $this->labRepo->listLabOrdersByDate($from, $to);
        foreach ($rows as &$row) {
            $row['validation_unlocked'] = $this->saveLabResultUseCase->isValidationUnlocked((string) $row['noorder']);
        }
        unset($row);

        return $this->json(['ok' => true, 'orders' => $rows]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function createLabOrder(array $payload): array
    {
        try {
            $noRawat = new NoRawat((string) ($payload['no_rawat'] ?? ''));
            $dokter = trim((string) ($payload['dokter_perujuk'] ?? ''));
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $status = trim((string) ($payload['status'] ?? 'ralan')) ?: 'ralan';

            $orders = $this->createLabUseCase->execute($noRawat, $dokter, $items, null, $status);

            return $this->json([
                'ok' => true,
                'orders' => array_map(static fn ($o): array => [
                    'no_order' => $o->noOrder,
                    'no_rawat' => $o->noRawat->value(),
                    'tgl_permintaan' => $o->tglPermintaan,
                    'jam_permintaan' => $o->jamPermintaan,
                    'jenis_klaim' => $o->jenisKlaim->value(),
                ], $orders),
            ], 201);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function saveLabResult(array $payload): array
    {
        try {
            $noOrder = trim((string) ($payload['no_order'] ?? ''));
            $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];

            $saved = $this->saveLabResultUseCase->execute($noOrder, $results);

            return $this->json([
                'ok' => true,
                'results' => array_map(static fn ($r): array => [
                    'no_rawat' => $r->noRawat->value(),
                    'kd_jenis_prw' => $r->kdJenisPrw,
                    'pemeriksaan' => $r->pemeriksaan,
                    'nilai' => $r->nilai,
                    'nilai_rujukan' => $r->nilaiRujukan,
                    'keterangan' => $r->keterangan,
                ], $saved),
            ]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function deleteLabOrder(array $payload): array
    {
        try {
            $noOrder = trim((string) ($payload['no_order'] ?? ''));
            $this->deleteLabUseCase->execute($noOrder);
            return $this->json(['ok' => true, 'deleted' => $noOrder]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function listRadiologyOrders(array $query): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today));
        $to = trim((string) ($query['to'] ?? $today));

        return $this->json([
            'ok' => true,
            'orders' => $this->labRepo->listRadiologiOrdersByDate($from, $to),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function createRadiologyOrder(array $payload): array
    {
        try {
            $noRawat = new NoRawat((string) ($payload['no_rawat'] ?? ''));
            $dokter = trim((string) ($payload['dokter_perujuk'] ?? ''));
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $status = trim((string) ($payload['status'] ?? 'ralan')) ?: 'ralan';

            $orders = $this->createRadiologyUseCase->execute($noRawat, $dokter, $items, null, $status);

            return $this->json([
                'ok' => true,
                'orders' => array_map(static fn ($o): array => [
                    'no_order' => $o->noOrder,
                    'no_rawat' => $o->noRawat->value(),
                    'tgl_permintaan' => $o->tglPermintaan,
                    'jam_permintaan' => $o->jamPermintaan,
                    'jenis_klaim' => $o->jenisKlaim->value(),
                ], $orders),
            ], 201);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function saveRadiologyResult(array $payload): array
    {
        try {
            $noOrder = trim((string) ($payload['no_order'] ?? ''));
            $hasil = trim((string) ($payload['hasil'] ?? ''));

            $saved = $this->saveRadiologyResultUseCase->execute($noOrder, $hasil);

            return $this->json([
                'ok' => true,
                'result' => [
                    'no_rawat' => $saved->noRawat->value(),
                    'tgl_periksa' => $saved->tglPeriksa,
                    'jam' => $saved->jam,
                    'hasil' => $saved->hasil,
                ],
            ]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function operationalReports(array $query): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today));
        $to = trim((string) ($query['to'] ?? $today));

        return $this->json([
            'ok' => true,
            'period' => ['from' => $from, 'to' => $to],
            'visits_by_poli' => $this->reportRepo->getVisitSummaryByPoli($from, $to),
            'top_diseases' => $this->reportRepo->getTopDiseases($from, $to, 10),
            'revenue_by_claim' => $this->reportRepo->getRevenueByClaimType($from, $to),
        ]);
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
