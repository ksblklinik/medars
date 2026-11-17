<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Rme\Repository\OperasiRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class OperasiController
{
    public function __construct(
        private OperasiRepositoryInterface $repo,
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

        $body = $this->renderer->render('operasi/index', [
            'judul' => 'Kamar Operasi & Bedah',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/operasi',
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
    public function apiPaketList(array $query = []): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listPaket($q)]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiPaketSave(array $post): array
    {
        $kode = trim((string) ($post['kode_paket'] ?? ''));
        $nama = trim((string) ($post['nm_perawatan'] ?? ''));
        if ($kode === '' || $nama === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Kode paket dan nama tindakan operasi wajib diisi.']);
        }
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->savePaket($post)]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBookingList(array $query = []): array
    {
        $from = trim((string) ($query['from'] ?? date('Y-m-d')));
        $to = trim((string) ($query['to'] ?? date('Y-m-d')));
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listBookings($from, $to)]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBookingSave(array $post): array
    {
        $noRawat = trim((string) ($post['no_rawat'] ?? ''));
        $kodePaket = trim((string) ($post['kode_paket'] ?? ''));
        if ($noRawat === '' || $kodePaket === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Rawat dan Paket Operasi wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveBooking($post)]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBookingStatus(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        $status = trim((string) ($post['status'] ?? ''));
        if ($id <= 0 || !in_array($status, ['Menunggu', 'Proses Operasi', 'Selesai', 'Batal'], true)) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID atau status jadwal operasi tidak valid.']);
        }
        if (!$this->repo->updateBookingStatus($id, $status)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Jadwal operasi tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Status jadwal operasi diperbarui.']);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiLaporanList(array $query = []): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listLaporan($q)]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiLaporanSave(array $post): array
    {
        $noRawat = trim((string) ($post['no_rawat'] ?? ''));
        $isi = trim((string) ($post['laporan_operasi'] ?? ''));
        if ($noRawat === '' || $isi === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Rawat dan uraian laporan operasi wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveLaporan($post)]);
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
