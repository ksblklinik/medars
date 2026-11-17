<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Billing\Repository\JasaMedisRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class JasaMedisController
{
    public function __construct(
        private JasaMedisRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('jasa_medis/index', [
            'judul' => 'Rekap Jasa Medis Dokter',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/jasa-medis',
            'csrf_token' => $csrfToken,
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
    public function apiSummary(array $query = []): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today)) ?: $today;
        $to = trim((string) ($query['to'] ?? $from)) ?: $from;
        $kdDokter = trim((string) ($query['kd_dokter'] ?? ''));

        return $this->jsonResponse([
            'status' => 'success',
            'period' => ['from' => $from, 'to' => $to],
            'summary' => $this->repo->summarizeByDoctor($from, $to, $kdDokter),
            'details' => $this->repo->listDetails($from, $to, $kdDokter),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printRekap(array $query = []): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today)) ?: $today;
        $to = trim((string) ($query['to'] ?? $from)) ?: $from;
        $rows = $this->repo->summarizeByDoctor($from, $to);

        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rowsHtml = '';
        foreach ($rows as $r) {
            $rowsHtml .= '<tr>'
                . '<td>' . $e($r['nm_dokter']) . ' (' . $e($r['kd_dokter']) . ')</td>'
                . '<td style="text-align:center">' . $e($r['jumlah_tindakan']) . '</td>'
                . '<td style="text-align:right">Rp ' . number_format((float) $r['jasa_tunai'], 0, ',', '.') . '</td>'
                . '<td style="text-align:right">Rp ' . number_format((float) $r['jasa_bpjs'], 0, ',', '.') . '</td>'
                . '<td style="text-align:right">Rp ' . number_format((float) $r['jasa_piutang'], 0, ',', '.') . '</td>'
                . '<td style="text-align:right"><strong>Rp ' . number_format((float) $r['total_jasa_dokter'], 0, ',', '.') . '</strong></td>'
                . '</tr>';
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Rekap Jasa Medis</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:20px;max-width:820px}h2{margin:0 0 4px;color:#00897b}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px;text-align:left}th{background:#f1f5f9}@media print{.no-print{display:none}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Rekap Jasa Medis</button></div>'
            . '<h2>REKAPITULASI JASA MEDIS DOKTER</h2>'
            . '<div style="font-size:13px;">Periode: ' . $e($from) . ' s/d ' . $e($to) . '</div>'
            . '<table><thead><tr><th>Dokter</th><th>Jml Tindakan</th><th>Tunai</th><th>BPJS</th><th>Piutang</th><th>Total Jasa</th></tr></thead><tbody>'
            . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="6">Belum ada transaksi jasa medis pada periode ini.</td></tr>')
            . '</tbody></table></body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    /**
     * @param mixed $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function jsonResponse(mixed $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($data, JSON_THROW_ON_ERROR),
        ];
    }
}
