<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Master\Repository\MasterDataRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class KepegawaianController
{
    public function __construct(
        private MasterDataRepositoryInterface $repo,
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

        $body = $this->renderer->render('kepegawaian/index', [
            'judul' => 'Manajemen Kepegawaian & SDM',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/kepegawaian',
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
    public function apiList(string $entity, array $query = []): array
    {
        if (!in_array($entity, ['pegawai', 'petugas'], true)) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Entitas SDM tidak valid.'], 422);
        }
        $q = trim((string) ($query['q'] ?? ''));
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->list($entity, $q)]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSave(string $entity, array $payload): array
    {
        try {
            if (!in_array($entity, ['pegawai', 'petugas'], true)) {
                throw new \InvalidArgumentException('Entitas SDM tidak valid.');
            }
            $this->repo->save($entity, $payload);
            return $this->jsonResponse(['status' => 'success', 'message' => "Data {$entity} berhasil disimpan."]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDelete(string $entity, array $payload): array
    {
        try {
            if (!in_array($entity, ['pegawai', 'petugas'], true)) {
                throw new \InvalidArgumentException('Entitas SDM tidak valid.');
            }
            $this->repo->delete($entity, $payload);
            return $this->jsonResponse(['status' => 'success', 'message' => "Data {$entity} berhasil dihapus."]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printDirectory(array $query = []): array
    {
        $entity = trim((string) ($query['entity'] ?? 'pegawai'));
        if (!in_array($entity, ['pegawai', 'petugas'], true)) {
            $entity = 'pegawai';
        }
        $rows = $this->repo->list($entity);
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rowsHtml = '';
        foreach ($rows as $idx => $r) {
            $id = $entity === 'pegawai' ? ($r['nik'] ?? '') : ($r['nip'] ?? '');
            $extra = $entity === 'pegawai' ? ($r['departemen'] ?? '-') : ($r['no_telp'] ?? '-');
            $rowsHtml .= '<tr>'
                . '<td>' . ($idx + 1) . '</td>'
                . '<td>' . $e($id) . '</td>'
                . '<td><strong>' . $e($r['nama'] ?? '-') . '</strong></td>'
                . '<td>' . $e($r['jbtn'] ?? '-') . '</td>'
                . '<td>' . $e($extra) . '</td>'
                . '<td>' . $e($r['status'] ?? '-') . '</td>'
                . '</tr>';
        }

        $title = $entity === 'pegawai' ? 'DAFTAR PEGAWAI KLINIK / RS' : 'DAFTAR PETUGAS MEDIS & PENUNJANG';
        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>' . $e($title) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:20px;max-width:800px}h2{margin:0 0 8px;color:#00897b}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px;text-align:left}th{background:#f1f5f9}@media print{.no-print{display:none}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Daftar</button></div>'
            . '<h2>' . $e($title) . '</h2>'
            . '<table><thead><tr><th>No</th><th>' . ($entity === 'pegawai' ? 'NIK' : 'NIP') . '</th><th>Nama</th><th>Jabatan</th><th>' . ($entity === 'pegawai' ? 'Departemen' : 'No. Telp') . '</th><th>Status</th></tr></thead><tbody>'
            . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="6">Belum ada data.</td></tr>')
            . '</tbody></table>'
            . '</body></html>';

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
