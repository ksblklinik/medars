<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Bridging\Repository\PcareRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class PcareController
{
    public function __construct(
        private PcareRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private NavigationBuilder $navBuilder
    ) {
    }

    public function index(?UserAccount $user, string $csrfToken): array
    {
        $html = $this->renderer->render('pcare/index', [
            'judul' => 'Bridging FKTP PCare BPJS',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'pcare',
            'module_icon' => 'fa-hospital-user',
            'user_name' => $user?->getFullname() ?: ($user?->getUsername() ?? 'Admin PCare'),
            'user_role' => $user?->getRole() ?? 'admin',
            'csrf_token' => $csrfToken,
            'navigations' => $this->navBuilder->buildForUser($user),
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    public function apiPendaftaranList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listPendaftaran(
                trim((string) ($query['from'] ?? date('Y-m-d'))),
                trim((string) ($query['to'] ?? date('Y-m-d'))),
                trim((string) ($query['q'] ?? ''))
            ),
        ]);
    }

    public function apiPendaftaranSave(array $post): array
    {
        if (trim((string) ($post['noKartu'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Kartu BPJS wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->savePendaftaran($post)]);
    }

    public function apiKunjunganList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listKunjungan(
                trim((string) ($query['from'] ?? date('Y-m-d'))),
                trim((string) ($query['to'] ?? date('Y-m-d'))),
                trim((string) ($query['q'] ?? ''))
            ),
        ]);
    }

    public function apiKunjunganSave(array $post): array
    {
        if (trim((string) ($post['noKartu'] ?? '')) === '' || trim((string) ($post['kdDiagnosa1'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Kartu BPJS dan Kode Diagnosa ICD-10 wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveKunjungan($post)]);
    }

    public function apiRujukanList(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listRujukan(trim((string) ($query['q'] ?? '')))]);
    }

    public function apiRujukanSave(array $post): array
    {
        if (trim((string) ($post['noKunjungan'] ?? '')) === '' || trim((string) ($post['nmppk'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Kunjungan PCare dan Faskes Rujukan wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveRujukan($post)]);
    }

    public function printRujukan(array $query = []): array
    {
        $noKunjungan = trim((string) ($query['no_kunjungan'] ?? $query['noKunjungan'] ?? ''));
        $r = $noKunjungan !== '' ? $this->repo->findRujukan($noKunjungan) : null;
        if ($r === null) {
            return ['status' => 404, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>Rujukan FKTP PCare tidak ditemukan.</h3>'];
        }
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Rujukan PCare ' . $e($r['noKunjungan']) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;max-width:700px;margin:20px auto;font-size:13px;}table{width:100%;border-collapse:collapse;}td,th{padding:6px;border:1px solid #ccc;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:10px;"><button onclick="window.print()">Cetak Rujukan PCare</button></div>'
            . '<h3 style="text-align:center;color:#00897b;margin:0;">SURAT RUJUKAN FKTP PCARE BPJS</h3>'
            . '<div style="text-align:center;font-weight:bold;margin:4px 0 12px;">No. Kunjungan: ' . $e($r['noKunjungan']) . '</div>'
            . '<table>'
            . '<tr><th align="left" width="28%">Kepada Yth. Faskes</th><td><strong>' . $e($r['nmppk']) . '</strong> (' . $e($r['kdppk']) . ')</td></tr>'
            . '<tr><th align="left">Sub Spesialis</th><td>' . $e($r['nmSubSpesialis'] ?: $r['kdSubSpesialis']) . '</td></tr>'
            . '<tr><th align="left">Nama Peserta / Kartu</th><td>' . $e($r['nmPasien'] ?? '-') . ' (' . $e($r['noKartu'] ?? '-') . ')</td></tr>'
            . '<tr><th align="left">Diagnosa ICD-10</th><td>' . $e($r['kdDiagnosa1'] ?? '-') . ' - ' . $e($r['nmDiagnosa1'] ?? '-') . '</td></tr>'
            . '<tr><th align="left">Est. Rencana Rujuk</th><td>' . $e($r['tglEstRujuk']) . '</td></tr>'
            . '<tr><th align="left">Catatan</th><td>' . $e($r['catatan'] ?: '-') . '</td></tr>'
            . '</table>'
            . '<div style="margin-top:26px;text-align:right;">Dokter FKTP<br><br><br><strong>' . $e($r['nmDokter'] ?? 'Dokter Pemeriksa') . '</strong></div>'
            . '</body></html>';

        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
    }

    public function apiObatList(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listObat(trim((string) ($query['noKunjungan'] ?? '')))]);
    }

    public function apiObatSave(array $post): array
    {
        if (trim((string) ($post['noKunjungan'] ?? '')) === '' || trim((string) ($post['nmObat'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Kunjungan dan Nama Obat wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveObat($post)]);
    }

    public function apiTindakanList(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listTindakan(trim((string) ($query['noKunjungan'] ?? '')))]);
    }

    public function apiTindakanSave(array $post): array
    {
        if (trim((string) ($post['noKunjungan'] ?? '')) === '' || trim((string) ($post['nmTindakan'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Kunjungan dan Nama Tindakan / Lab wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveTindakan($post)]);
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
