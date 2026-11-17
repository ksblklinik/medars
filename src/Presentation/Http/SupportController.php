<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Support\UseCase\CreateLabOrderUseCase;
use App\Application\Support\UseCase\CreateRadiologyOrderUseCase;
use App\Application\Support\UseCase\DeleteLabOrderUseCase;
use App\Application\Support\UseCase\DeleteRadiologyOrderUseCase;
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
        private ?DeleteRadiologyOrderUseCase $deleteRadUseCase = null,
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
            'reconciliation' => $this->reportRepo->getOrderReconciliationReport($from, $to),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function exportOperationalCsv(array $query): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today));
        $to = trim((string) ($query['to'] ?? $today));

        $lines = ["Section,Key,Label,Count,Amount"];
        foreach ($this->reportRepo->getVisitSummaryByPoli($from, $to) as $v) {
            $lines[] = sprintf('Kunjungan,"%s","%s",%d,0', $v['kd_poli'], str_replace('"', '""', $v['nm_poli']), $v['total_kunjungan']);
        }
        foreach ($this->reportRepo->getRevenueByClaimType($from, $to) as $r) {
            $lines[] = sprintf('Pendapatan,"%s","%s",%d,%.2f', $r['claim_type'], $r['claim_type'], $r['total_items'], $r['total_amount']);
        }
        foreach ($this->reportRepo->getOrderReconciliationReport($from, $to) as $o) {
            $lines[] = sprintf('Rekonsiliasi,"%s","%s (%s)",1,%.2f', $o['no_rawat'], str_replace('"', '""', (string) $o['item_name']), $o['status'], (float) $o['total_amount']);
        }

        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"laporan_operasional_{$from}_{$to}.csv\"",
            ],
            'body' => implode("\n", $lines) . "\n",
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function deleteRadiologyOrder(array $payload): array
    {
        try {
            if ($this->deleteRadUseCase === null) {
                throw new \DomainException('Layanan hapus order radiologi belum dikonfigurasi.');
            }
            $this->deleteRadUseCase->execute(trim((string) ($payload['noorder'] ?? '')));
            return $this->json(['ok' => true, 'message' => 'Order radiologi berhasil dihapus.']);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printLabResult(array $query): array
    {
        $noOrder = trim((string) ($query['noorder'] ?? ''));
        $order = $noOrder !== '' ? $this->labRepo->findLabOrder($noOrder) : null;
        if ($order === null) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Order laboratorium tidak ditemukan.</h3>',
            ];
        }

        $results = $this->labRepo->findLabResultsByNoRawat($order->noRawat);
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rowsHtml = '';
        foreach ($results as $r) {
            $rowsHtml .= '<tr>'
                . '<td>' . $e($r->kdJenisPrw) . '</td>'
                . '<td>' . $e($r->idTemplate) . '</td>'
                . '<td><strong>' . $e($r->nilai) . '</strong></td>'
                . '<td>' . $e($r->nilaiRujukan) . '</td>'
                . '<td>' . $e($r->keterangan) . '</td>'
                . '</tr>';
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Hasil Lab ' . $e($noOrder) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:20px;max-width:760px}h2{margin:0 0 6px;color:#00897b}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px;text-align:left}th{background:#f1f5f9}@media print{.no-print{display:none}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Hasil Lab</button></div>'
            . '<h2>HASIL PEMERIKSAAN LABORATORIUM</h2>'
            . '<div style="font-size:13px;">No. Order: <strong>' . $e($order->noOrder) . '</strong> | No. Rawat: ' . $e($order->noRawat->value()) . ' | Tgl. Order: ' . $e($order->tglPermintaan) . '</div>'
            . '<table><thead><tr><th>Kode</th><th>Parameter</th><th>Hasil</th><th>Nilai Rujukan</th><th>Keterangan</th></tr></thead><tbody>'
            . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="5">Belum ada hasil laboratorium tercatat.</td></tr>')
            . '</tbody></table>'
            . '<div style="margin-top:28px;text-align:right;font-size:13px;">Analis / Penanggung Jawab Lab<br><br><br>(__________________________)</div>'
            . '</body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printRadiologyResult(array $query): array
    {
        $noOrder = trim((string) ($query['noorder'] ?? ''));
        $order = $noOrder !== '' ? $this->labRepo->findRadiologiOrder($noOrder) : null;
        if ($order === null) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Order radiologi tidak ditemukan.</h3>',
            ];
        }

        $results = $this->labRepo->findRadiologiResultsByNoRawat($order->noRawat);
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $blocks = '';
        foreach ($results as $r) {
            $blocks .= '<div style="border:1px solid #ccc;padding:12px;margin-top:12px;background:#fafafa;">'
                . '<div style="font-size:12px;color:#555;margin-bottom:6px;">Tanggal Periksa: ' . $e($r->tglPeriksa) . ' ' . $e($r->jam) . '</div>'
                . '<div style="white-space:pre-wrap;font-size:14px;">' . $e($r->hasil) . '</div>'
                . '</div>';
        }
        if ($blocks === '') {
            $blocks = '<p>Belum ada expertise radiologi tercatat.</p>';
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Expertise Radiologi ' . $e($noOrder) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:20px;max-width:760px}h2{margin:0 0 6px;color:#00897b}@media print{.no-print{display:none}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Expertise Radiologi</button></div>'
            . '<h2>HASIL EXPERTISE RADIOLOGI</h2>'
            . '<div style="font-size:13px;">No. Order: <strong>' . $e($order->noOrder) . '</strong> | No. Rawat: ' . $e($order->noRawat->value()) . ' | Tgl. Order: ' . $e($order->tglPermintaan) . '</div>'
            . $blocks
            . '<div style="margin-top:28px;text-align:right;font-size:13px;">Dokter Spesialis Radiologi<br><br><br>(__________________________)</div>'
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
