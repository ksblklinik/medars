<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Bridging\UseCase\ProcessBridgingQueueUseCase;
use App\Application\Bridging\UseCase\QueueSatuSehatSyncUseCase;
use App\Application\Bridging\UseCase\VerifyVedikaClaimUseCase;
use App\Domain\Bridging\Repository\BridgingQueueRepositoryInterface;
use App\Domain\Bridging\Repository\VedikaRepositoryInterface;
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
        private ?VedikaRepositoryInterface $vedikaRepo = null,
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
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function listVedikaClaims(array $query = []): array
    {
        if ($this->vedikaRepo === null) {
            return $this->json(['ok' => true, 'claims' => []]);
        }
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? date('Y-m-01'))) ?: date('Y-m-01');
        $to = trim((string) ($query['to'] ?? $today)) ?: $today;
        $status = trim((string) ($query['status'] ?? ''));

        return $this->json(['ok' => true, 'claims' => $this->vedikaRepo->listClaims($from, $to, $status)]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printVedikaBundle(array $query = []): array
    {
        $nr = trim((string) ($query['no_rawat'] ?? ''));
        $bundle = ($nr !== '' && $this->vedikaRepo !== null) ? $this->vedikaRepo->getBundleData($nr) : null;
        if ($bundle === null) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Bundel klaim Vedika tidak ditemukan.</h3>',
            ];
        }

        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $v = $bundle['visit'];
        $soapHtml = '';
        foreach ($bundle['soap'] as $s) {
            $soapHtml .= '<li><strong>' . $e($s['tgl_perawatan']) . ' ' . $e($s['jam_rawat']) . '</strong> — S: ' . $e($s['keluhan']) . ' | O: ' . $e($s['pemeriksaan']) . ' | A: ' . $e($s['penilaian']) . ' | P: ' . $e($s['rtl']) . '</li>';
        }
        $diagHtml = '';
        foreach ($bundle['diagnosa'] as $d) {
            $diagHtml .= '<li><code>' . $e($d['kd_penyakit']) . '</code> ' . $e($d['nm_penyakit']) . '</li>';
        }
        $billHtml = '';
        $total = 0.0;
        foreach ($bundle['billing'] as $b) {
            $total += (float) $b['total_amount'];
            $billHtml .= '<tr><td>' . $e($b['item_name']) . '</td><td>' . $e($b['category']) . '</td><td>' . $e($b['claim_type']) . '</td><td style="text-align:right">Rp ' . number_format((float) $b['total_amount'], 0, ',', '.') . '</td></tr>';
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Bundel Vedika ' . $e($nr) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:20px;max-width:800px;font-size:13px}h2,h4{color:#00897b;margin:8px 0 4px}table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left}th{background:#f1f5f9}@media print{.no-print{display:none}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:10px;"><button onclick="window.print()">Cetak Bundel Klaim Vedika</button></div>'
            . '<h2>BUNDEL BERKAS KLAIM VEDIKA BPJS</h2>'
            . '<div>No. Rawat: <strong>' . $e($v['no_rawat']) . '</strong> | No. SEP: <strong>' . $e($v['no_sep'] ?? '-') . '</strong> | Status: <strong>' . $e($v['vedika_status'] ?? 'Belum Verifikasi') . '</strong><br>'
            . 'Pasien: <strong>' . $e($v['nm_pasien']) . '</strong> (' . $e($v['no_rkm_medis']) . ') | Poli / Dokter: ' . $e($v['nm_poli']) . ' / ' . $e($v['nm_dokter']) . '</div>'
            . '<h4>1. Diagnosa ICD-10</h4><ul>' . ($diagHtml ?: '<li>Belum ada diagnosa.</li>') . '</ul>'
            . '<h4>2. Resume SOAP Klinis</h4><ul>' . ($soapHtml ?: '<li>Belum ada SOAP.</li>') . '</ul>'
            . '<h4>3. Rincian Billing</h4><table><thead><tr><th>Item</th><th>Kategori</th><th>Pilar</th><th style="text-align:right">Total</th></tr></thead><tbody>'
            . ($billHtml ?: '<tr><td colspan="4">Belum ada item billing.</td></tr>')
            . '</tbody><tfoot><tr><th colspan="3">Total Tagihan</th><th style="text-align:right">Rp ' . number_format($total, 0, ',', '.') . '</th></tr></tfoot></table>'
            . '</body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
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
