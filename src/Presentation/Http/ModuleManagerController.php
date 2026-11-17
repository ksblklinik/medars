<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\ModuleRepositoryInterface;
use App\Presentation\View\ViewRenderer;
use InvalidArgumentException;

final readonly class ModuleManagerController
{
    public function __construct(
        private ModuleRepositoryInterface $repo,
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

        $body = $this->renderer->render('modules/index', [
            'judul' => 'Manajer Modul SIMRS',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/modules',
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
        $filter = trim((string) ($query['filter'] ?? 'all'));
        if (!in_array($filter, ['all', 'active', 'inactive'], true)) {
            $filter = 'all';
        }
        $q = trim((string) ($query['q'] ?? ''));

        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listModules($filter, $q),
        ]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiToggle(array $post): array
    {
        $dir = trim((string) ($post['dir'] ?? ''));
        if ($dir === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Kode modul wajib diisi.']);
        }
        $active = filter_var($post['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        try {
            $ok = $this->repo->setActive($dir, $active);
        } catch (InvalidArgumentException $e) {
            return $this->json(422, ['status' => 'error', 'message' => $e->getMessage()]);
        }

        if (!$ok) {
            return $this->json(404, ['status' => 'error', 'message' => 'Modul tidak ditemukan.']);
        }

        return $this->json(200, [
            'status' => 'success',
            'message' => $active ? "Modul {$dir} diaktifkan." : "Modul {$dir} dinonaktifkan.",
        ]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSequence(array $post): array
    {
        $dir = trim((string) ($post['dir'] ?? ''));
        $sequence = (int) ($post['sequence'] ?? 0);
        if ($dir === '' || $sequence < 0) {
            return $this->json(422, ['status' => 'error', 'message' => 'Parameter urutan modul tidak valid.']);
        }
        if (!$this->repo->updateSequence($dir, $sequence)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Modul tidak ditemukan.']);
        }

        return $this->json(200, ['status' => 'success', 'message' => 'Urutan modul diperbarui.']);
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
