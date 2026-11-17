<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Clinical\Repository\RiwayatSimgosRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class RiwayatSimgosController
{
    public function __construct(
        private RiwayatSimgosRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private NavigationBuilder $navBuilder
    ) {
    }

    public function index(?UserAccount $user, string $csrfToken): array
    {
        $html = $this->renderer->render('riwayat_simgos/index', [
            'judul' => 'Viewer Riwayat Migrasi SIMGOS',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'riwayat_simgos',
            'module_icon' => 'fa-history',
            'user_name' => $user?->getFullname() ?: ($user?->getUsername() ?? 'Admin'),
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

    public function apiList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->search(
                trim((string) ($query['q'] ?? '')),
                trim((string) ($query['from'] ?? '')),
                trim((string) ($query['to'] ?? ''))
            ),
        ]);
    }

    public function apiImport(array $post): array
    {
        if (trim((string) ($post['nopen'] ?? '')) === '' || trim((string) ($post['nm_pasien'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Pendaftaran (NOPEN) dan Nama Pasien wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->importRecord($post)]);
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
