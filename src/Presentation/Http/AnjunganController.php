<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Queue\Repository\AnjunganQueueRepositoryInterface;
use App\Infrastructure\Realtime\EventBroadcasterInterface;
use App\Infrastructure\Realtime\EventMessage;

final readonly class AnjunganController
{
    public function __construct(
        private AnjunganQueueRepositoryInterface $repo,
        private ?EventBroadcasterInterface $broadcaster = null,
    ) {
    }

    public function apiIssueTicket(array $post): array
    {
        $type = trim((string) ($post['type'] ?? 'Poli')) ?: 'Poli';
        $ticket = $this->repo->issueTicket(
            $type,
            (string) ($post['no_rawat'] ?? ''),
            (string) ($post['no_rkm_medis'] ?? ''),
            (string) ($post['nm_pasien'] ?? ''),
            (string) ($post['nama_loket'] ?? '')
        );
        return $this->json(201, ['status' => 'success', 'data' => $ticket]);
    }

    public function apiListQueue(array $query = []): array
    {
        $type = trim((string) ($query['type'] ?? 'Farmasi')) ?: 'Farmasi';
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listByType($type, trim((string) ($query['date'] ?? ''))),
        ]);
    }

    public function apiCallNext(array $post): array
    {
        $type = trim((string) ($post['type'] ?? 'Farmasi')) ?: 'Farmasi';
        $row = $this->repo->callNext($type, (string) ($post['nama_loket'] ?? ''));
        if ($row === null) {
            return $this->json(404, ['status' => 'error', 'message' => 'Tidak ada antrean menunggu untuk ' . $type . '.']);
        }
        if ($this->broadcaster !== null) {
            $loket = (string) ($row['nama_loket'] ?: ($type . ' 1'));
            $noAntrean = (string) $row['noantrian'];
            $this->broadcaster->publish('antrean', new EventMessage(
                event: 'queue.called',
                data: [
                    'kd_poli' => strtoupper($type),
                    'nm_poli' => $type,
                    'no_reg' => $noAntrean,
                    'room' => $loket,
                    'sound_text' => "Nomor antrean {$noAntrean}, silakan menuju ke {$loket}",
                    'timestamp' => date('c'),
                ]
            ));
        }
        return $this->json(200, ['status' => 'success', 'data' => $row]);
    }

    public function printThermalTicket(array $query = []): array
    {
        $id = (int) ($query['id'] ?? 0);
        $ticket = $id > 0 ? $this->repo->findTicket($id) : null;
        if ($ticket === null) {
            return ['status' => 404, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>Tiket antrean tidak ditemukan.</h3>'];
        }
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Tiket ' . $e($ticket['noantrian']) . '</title>'
            . '<style>body{font-family:monospace;width:280px;margin:10px auto;text-align:center;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:8px;"><button onclick="window.print()">Cetak Tiket Thermal</button></div>'
            . '<h4 style="margin:0;">SIMRS MEDARS</h4><div>TIKET ANTREAN ' . $e(strtoupper((string) $ticket['type'])) . '</div><hr>'
            . '<div style="font-size:36px;font-weight:bold;margin:8px 0;">' . $e($ticket['noantrian']) . '</div>'
            . '<div>Loket: ' . $e($ticket['nama_loket']) . '</div>'
            . ($ticket['nm_pasien'] !== '' ? '<div>Pasien: ' . $e($ticket['nm_pasien']) . '</div>' : '')
            . '<div style="font-size:11px;margin-top:6px;">' . $e($ticket['postdate']) . ' ' . $e($ticket['start_time']) . '</div>'
            . '</body></html>';
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
    }

    public function displayLeanTv(): array
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $poli = $this->repo->listByType('Poli');
        $farmasi = $this->repo->listByType('Farmasi');
        $kasir = $this->repo->listByType('Kasir');
        $last = static fn (array $rows): string => $rows === [] ? '-' : (string) $rows[array_key_last($rows)]['noantrian'];
        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta http-equiv="refresh" content="15"><title>Display LEAN TV — Medars</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#0f172a;color:#fff;margin:0;padding:24px;}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}.card{background:#1e293b;border-radius:10px;padding:20px;text-align:center;border-top:5px solid #00897b;}.num{font-size:52px;font-weight:bold;color:#38bdf8;margin:12px 0;}</style></head><body>'
            . '<h2 style="margin:0 0 18px;color:#2dd4bf;">DISPLAY LEAN TV &amp; ANTREAN TERPADU — SIMRS MEDARS</h2>'
            . '<div class="grid">'
            . '<div class="card"><h3>ANTREAN POLIKLINIK</h3><div class="num">' . $e($last($poli)) . '</div><div>Total Hari Ini: ' . count($poli) . '</div></div>'
            . '<div class="card"><h3>ANTREAN FARMASI</h3><div class="num">' . $e($last($farmasi)) . '</div><div>Total Hari Ini: ' . count($farmasi) . '</div></div>'
            . '<div class="card"><h3>ANTREAN KASIR</h3><div class="num">' . $e($last($kasir)) . '</div><div>Total Hari Ini: ' . count($kasir) . '</div></div>'
            . '</div></body></html>';
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
    }

    public function displayUnitTv(string $type): array
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $rows = $this->repo->listByType($type);
        $listHtml = '';
        foreach ($rows as $r) {
            $listHtml .= '<tr><td><strong>' . $e($r['noantrian']) . '</strong></td><td>' . $e($r['nama_loket']) . '</td><td>' . $e($r['nm_pasien'] ?: '-') . '</td><td>' . ((int) $r['status'] === 1 ? 'Dipanggil' : 'Menunggu') . '</td></tr>';
        }
        if ($listHtml === '') {
            $listHtml = '<tr><td colspan="4" align="center">Belum ada antrean ' . $e($type) . ' hari ini.</td></tr>';
        }
        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta http-equiv="refresh" content="10"><title>Display Antrean ' . $e($type) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#0f172a;color:#fff;padding:24px;}table{width:100%;border-collapse:collapse;background:#1e293b;}th,td{padding:10px;border-bottom:1px solid #334155;text-align:left;}</style></head><body>'
            . '<h2 style="color:#2dd4bf;">DISPLAY ANTREAN ' . $e(strtoupper($type)) . '</h2>'
            . '<table><thead><tr><th>No. Antrean</th><th>Loket</th><th>Pasien</th><th>Status</th></tr></thead><tbody>' . $listHtml . '</tbody></table></body></html>';
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
    }

    private function json(int $status, array $payload): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
