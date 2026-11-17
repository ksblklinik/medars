<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Rme\Repository\RuangTindakanRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class RuangTindakanController
{
    public function __construct(
        private RuangTindakanRepositoryInterface $repo,
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

        $body = $this->renderer->render('ruang_tindakan/index', [
            'judul' => 'Ruang Tindakan Medis & Keperawatan',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/ruang-tindakan',
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
    public function apiList(array $query = []): array
    {
        $from = trim((string) ($query['from'] ?? date('Y-m-d')));
        $to = trim((string) ($query['to'] ?? date('Y-m-d')));
        $q = trim((string) ($query['q'] ?? ''));

        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listFollowup($from, $to, $q),
        ]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSave(?UserAccount $actor, array $post): array
    {
        $noRawat = trim((string) ($post['no_rawat'] ?? ''));
        $kdJenis = trim((string) ($post['kd_jenis_prw'] ?? ''));
        if ($noRawat === '' || $kdJenis === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Rawat dan Kode Tindakan wajib diisi.']);
        }

        $post['nm_petugas'] = $actor?->getFullname() ?: ($actor?->getUsername() ?? 'Sistem');
        $saved = $this->repo->saveFollowup($post, $actor?->getRole() ?? 'medis');

        return $this->json(200, ['status' => 'success', 'data' => $saved]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDelete(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0 || !$this->repo->deleteFollowup($id)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Data tindakan tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Data tindakan dihapus.']);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printLaporan(array $query = []): array
    {
        $id = (int) ($query['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findById($id) : null;
        if ($row === null) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Laporan tindakan tidak ditemukan.</h3>',
            ];
        }

        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Laporan Ruang Tindakan</title>'
            . '<style>body{font-family:Arial,sans-serif;max-width:720px;margin:24px auto;font-size:13px;}table{width:100%;border-collapse:collapse;}td,th{padding:6px;border:1px solid #ddd;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Laporan</button></div>'
            . '<h3 style="text-align:center;margin-bottom:4px;">LAPORAN TINDAKAN MEDIS / KEPERAWATAN</h3><hr>'
            . '<table>'
            . '<tr><th align="left" width="30%">No. Rawat</th><td>' . $e($row['no_rawat']) . '</td></tr>'
            . '<tr><th align="left">Kode Tindakan</th><td>' . $e($row['kd_jenis_prw']) . '</td></tr>'
            . '<tr><th align="left">Tanggal Tindakan</th><td>' . $e($row['tgl_tindakan']) . '</td></tr>'
            . '<tr><th align="left">Diagnosis</th><td>' . $e($row['diagnosis']) . '</td></tr>'
            . '<tr><th align="left">Laporan Tindakan</th><td>' . nl2br($e($row['laporan_operasi'])) . '</td></tr>'
            . '<tr><th align="left">Catatan Follow-Up</th><td>' . nl2br($e($row['catatan'])) . '</td></tr>'
            . '<tr><th align="left">Dokter / Petugas</th><td>' . $e($row['kd_dokter']) . ' / ' . $e($row['nm_petugas']) . '</td></tr>'
            . '<tr><th align="left">Status</th><td><strong>' . $e($row['status']) . '</strong></td></tr>'
            . '</table></body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function json(int $status, array $payload): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
