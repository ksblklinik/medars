<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\UtdRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class UtdController
{
    public function __construct(
        private UtdRepositoryInterface $repo,
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

        $body = $this->renderer->render('utd/index', [
            'judul' => 'Unit Transfusi Darah (UTD)',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/utd',
            'csrf_token' => $csrfToken,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    public function apiKomponenList(): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listKomponen()]);
    }

    public function apiKomponenSave(array $post): array
    {
        if (trim((string) ($post['kode'] ?? '')) === '' || trim((string) ($post['nama'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Kode dan nama komponen wajib diisi.']);
        }
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->saveKomponen($post)]);
    }

    public function apiPendonorList(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listPendonor((string) ($query['q'] ?? ''))]);
    }

    public function apiPendonorSave(array $post): array
    {
        if (trim((string) ($post['no_pendonor'] ?? '')) === '' || trim((string) ($post['nama'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Pendonor dan nama wajib diisi.']);
        }
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->savePendonor($post)]);
    }

    public function apiStokList(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listStokDarah((string) ($query['golongan'] ?? ''))]);
    }

    public function apiStokSave(array $post): array
    {
        if (trim((string) ($post['no_kantong'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Kantong darah wajib diisi.']);
        }
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->saveStokDarah($post)]);
    }

    public function apiPermintaanList(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listPermintaan((string) ($query['q'] ?? ''))]);
    }

    public function apiPermintaanSave(?UserAccount $actor, array $post): array
    {
        if (trim((string) ($post['no_rawat'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Rawat wajib diisi.']);
        }
        $post['petugas'] = $actor?->getFullname() ?: ($actor?->getUsername() ?? 'Sistem');
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->savePermintaan($post)]);
    }

    public function apiCrossmatch(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        $noKantong = trim((string) ($post['no_kantong'] ?? ''));
        $hasil = trim((string) ($post['hasil_crossmatch'] ?? 'Cocok'));
        $status = trim((string) ($post['status'] ?? 'Diserahkan'));
        if ($id <= 0 || !$this->repo->updateCrossmatch($id, $noKantong, $hasil, $status)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Permintaan darah tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Hasil crossmatch & status kantong diperbarui.']);
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
