<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Repository\ApiKeyRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class ApiKeyController
{
    public function __construct(
        private ApiKeyRepositoryInterface $repo,
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

        $body = $this->renderer->render('api_keys/index', [
            'judul' => 'Manajemen API Key Eksternal',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/api-keys',
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
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listKeys($q),
        ]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCreate(array $post): array
    {
        $label = trim((string) ($post['label'] ?? ''));
        if ($label === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Label API Key wajib diisi.']);
        }

        $methods = $post['methods'] ?? 'GET';
        if (is_array($methods)) {
            $methods = implode(',', array_map('strval', $methods));
        }

        $created = $this->repo->createKey([
            'label' => $label,
            'username' => trim((string) ($post['username'] ?? '')),
            'methods' => trim((string) $methods) !== '' ? trim((string) $methods) : 'GET',
            'ip_range' => trim((string) ($post['ip_range'] ?? '*')) ?: '*',
            'exp_time' => trim((string) ($post['exp_time'] ?? '')),
        ]);

        return $this->json(201, [
            'status' => 'success',
            'message' => 'API Key berhasil dibuat. Simpan raw_key karena hanya ditampilkan sekali.',
            'data' => $created,
        ]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiToggle(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID API Key tidak valid.']);
        }
        $active = filter_var($post['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$this->repo->toggleActive($id, $active)) {
            return $this->json(404, ['status' => 'error', 'message' => 'API Key tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Status API Key diperbarui.']);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDelete(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID API Key tidak valid.']);
        }
        if (!$this->repo->deleteKey($id)) {
            return $this->json(404, ['status' => 'error', 'message' => 'API Key tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'API Key berhasil dihapus.']);
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
