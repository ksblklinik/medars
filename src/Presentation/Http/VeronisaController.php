<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Bridging\Repository\VeronisaRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class VeronisaController
{
    public function __construct(
        private VeronisaRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {
    }

    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('veronisa/index', [
            'judul' => 'Veronisa — Verifikasi Internal & Non-Kapitasi',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/veronisa',
            'csrf_token' => $csrfToken,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    public function apiClaimsList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listClaims(
                trim((string) ($query['from'] ?? date('Y-m-d'))),
                trim((string) ($query['to'] ?? date('Y-m-d'))),
                trim((string) ($query['q'] ?? ''))
            ),
        ]);
    }

    public function apiClaimSave(?UserAccount $actor, array $post): array
    {
        if (trim((string) ($post['no_rawat'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Rawat wajib diisi.']);
        }
        $post['username'] = $actor?->getUsername() ?? 'system';
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveClaim($post)]);
    }

    public function apiClaimStatus(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        $status = trim((string) ($post['status'] ?? ''));
        $catatan = trim((string) ($post['catatan'] ?? ''));
        if ($id <= 0 || !in_array($status, ['Pengajuan', 'Layak', 'Perbaiki', 'Disetujui'], true)) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID atau status verifikasi tidak valid.']);
        }
        if (!$this->repo->updateStatus($id, $status, $catatan)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Data pengajuan tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Status verifikasi diperbarui.']);
    }

    public function apiClaimDelete(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0 || !$this->repo->deleteClaim($id)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Data pengajuan tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Data pengajuan dihapus.']);
    }

    public function apiMappingList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listObatMapping(trim((string) ($query['q'] ?? ''))),
        ]);
    }

    public function apiMappingSave(array $post): array
    {
        if (trim((string) ($post['kode_brng'] ?? '')) === '' || trim((string) ($post['kode_dpho'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Kode barang dan kode DPHO wajib diisi.']);
        }
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->saveObatMapping($post)]);
    }

    public function printBerkas(array $query = []): array
    {
        $id = (int) ($query['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findClaimById($id) : null;
        if ($row === null) {
            return ['status' => 404, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>Berkas veronisa tidak ditemukan.</h3>'];
        }
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Berkas Veronisa #' . $id . '</title>'
            . '<style>body{font-family:Arial,sans-serif;max-width:720px;margin:24px auto;font-size:13px;}table{width:100%;border-collapse:collapse;}td,th{padding:6px;border:1px solid #ddd;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Berkas</button></div>'
            . '<h3 style="text-align:center;">BERKAS VERIFIKASI INTERNAL KLAIM (VERONISA)</h3><hr>'
            . '<table>'
            . '<tr><th align="left" width="30%">Tanggal Pengajuan</th><td>' . $e($row['tanggal']) . '</td></tr>'
            . '<tr><th align="left">No. Rawat</th><td>' . $e($row['no_rawat']) . '</td></tr>'
            . '<tr><th align="left">No. Rekam Medis</th><td>' . $e($row['no_rkm_medis']) . '</td></tr>'
            . '<tr><th align="left">No. SEP</th><td>' . $e($row['nosep']) . '</td></tr>'
            . '<tr><th align="left">Status Verifikasi</th><td><strong>' . $e($row['status']) . '</strong></td></tr>'
            . '<tr><th align="left">Catatan Verifikator</th><td>' . nl2br($e($row['catatan'])) . '</td></tr>'
            . '<tr><th align="left">Petugas</th><td>' . $e($row['username']) . '</td></tr>'
            . '</table></body></html>';

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
