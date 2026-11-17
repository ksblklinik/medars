<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\InventarisRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class InventarisController
{
    public function __construct(
        private InventarisRepositoryInterface $repo,
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

        $body = $this->renderer->render('inventaris/index', [
            'judul' => 'Manajemen Inventaris & Aset Klinik',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/inventaris',
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
        $q = trim((string) ($query['q'] ?? ''));
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listAset($q)]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSave(array $payload): array
    {
        try {
            $this->repo->saveAset($payload);
            return $this->jsonResponse(['status' => 'success', 'message' => 'Data aset inventaris berhasil disimpan.']);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDelete(array $payload): array
    {
        try {
            $this->repo->deleteAset((string) ($payload['kode_aset'] ?? ''));
            return $this->jsonResponse(['status' => 'success', 'message' => 'Data aset inventaris berhasil dihapus.']);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
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
