<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Bridging\Repository\VclaimRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;

final readonly class VclaimController
{
    public function __construct(private VclaimRepositoryInterface $repo)
    {
    }

    public function apiCariPeserta(array $query = []): array
    {
        $q = trim((string) ($query['no_kartu'] ?? $query['q'] ?? ''));
        if ($q === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Nomor kartu BPJS / NIK wajib diisi.']);
        }
        $row = $this->repo->findPesertaLocal($q);
        if ($row === null) {
            return $this->json(404, ['status' => 'error', 'message' => 'Peserta tidak ditemukan di data lokal SIMRS.']);
        }
        return $this->json(200, ['status' => 'success', 'data' => $row]);
    }

    public function apiSepList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listSep(
                trim((string) ($query['from'] ?? date('Y-m-d'))),
                trim((string) ($query['to'] ?? date('Y-m-d'))),
                trim((string) ($query['q'] ?? ''))
            ),
        ]);
    }

    public function apiSepSave(?UserAccount $actor, array $post): array
    {
        $nr = trim((string) ($post['no_rawat'] ?? ''));
        $nk = trim((string) ($post['no_kartu'] ?? ''));
        if ($nr === '' || $nk === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Rawat dan No. Kartu BPJS wajib diisi.']);
        }
        $post['user_input'] = $actor?->getUsername() ?? 'system';
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveSep($post)]);
    }

    public function printSep(array $query = []): array
    {
        $noSep = trim((string) ($query['no_sep'] ?? ''));
        $sep = $noSep !== '' ? $this->repo->findSep($noSep) : null;
        if ($sep === null) {
            return ['status' => 404, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>SEP BPJS tidak ditemukan.</h3>'];
        }
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>SEP ' . $e($sep['no_sep']) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;max-width:720px;margin:20px auto;font-size:13px;}table{width:100%;border-collapse:collapse;}td,th{padding:5px;border:1px solid #ccc;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:10px;"><button onclick="window.print()">Cetak SEP</button></div>'
            . '<h3 style="text-align:center;color:#00897b;margin:0;">SURAT ELEGIBILITAS PESERTA (SEP) BPJS</h3>'
            . '<div style="text-align:center;font-size:15px;font-weight:bold;margin:4px 0 12px;">' . $e($sep['no_sep']) . '</div>'
            . '<table>'
            . '<tr><th align="left" width="25%">Tgl. SEP</th><td>' . $e($sep['tglsep']) . '</td><th align="left" width="25%">No. Rawat</th><td>' . $e($sep['no_rawat']) . '</td></tr>'
            . '<tr><th align="left">No. Kartu BPJS</th><td>' . $e($sep['no_kartu']) . '</td><th align="left">Nama Peserta</th><td><strong>' . $e($sep['nama_pasien']) . '</strong></td></tr>'
            . '<tr><th align="left">Poli Tujuan</th><td>' . $e($sep['nmpolitujuan'] ?: $sep['kdpolitujuan']) . '</td><th align="left">Kelas Rawat</th><td>Kelas ' . $e($sep['klsrawat']) . '</td></tr>'
            . '<tr><th align="left">Diagnosa Awal</th><td colspan="3">' . $e($sep['diagawal']) . ' - ' . $e($sep['nmdiagnosaawal']) . '</td></tr>'
            . '<tr><th align="left">Catatan</th><td colspan="3">' . $e($sep['catatan'] ?: '-') . '</td></tr>'
            . '</table>'
            . '<div style="margin-top:28px;text-align:right;">Petugas BPJS / Verifikator<br><br><br><strong>' . $e($sep['user_input']) . '</strong></div>'
            . '</body></html>';
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
    }

    public function apiRujukanList(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listRujukan(trim((string) ($query['q'] ?? '')))]);
    }

    public function apiRujukanSave(array $post): array
    {
        if (trim((string) ($post['no_sep'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. SEP asal wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveRujukan($post)]);
    }

    public function apiSuratKontrolList(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listSuratKontrol(trim((string) ($query['q'] ?? '')))]);
    }

    public function apiSuratKontrolSave(array $post): array
    {
        if (trim((string) ($post['no_sep'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. SEP wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveSuratKontrol($post)]);
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
