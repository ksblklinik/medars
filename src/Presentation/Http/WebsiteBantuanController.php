<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\WebsiteCmsRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class WebsiteBantuanController
{
    public function __construct(
        private WebsiteCmsRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private NavigationBuilder $navBuilder
    ) {
    }

    public function publicIndex(): array
    {
        $html = $this->renderer->render('website/public', [
            'judul' => 'SIMRS Medars — Layanan Kesehatan Terpadu',
            'klinik_nama' => 'SIMRS Medars',
            'pages' => $this->repo->listPages(true),
        ], false);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    public function adminWebsite(?UserAccount $user, string $csrfToken): array
    {
        $html = $this->renderer->render('website/cms', [
            'judul' => 'CMS Website Klinik Publik',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'website',
            'module_icon' => 'fa-globe',
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

    public function apiPagesList(): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listPages(false)]);
    }

    public function apiPageSave(array $post): array
    {
        if (trim((string) ($post['title'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Judul halaman wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->savePage($post)]);
    }

    public function bantuanIndex(?UserAccount $user): array
    {
        $html = $this->renderer->render('bantuan/index', [
            'judul' => 'Pusat Bantuan & Dokumentasi Internal',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'bantuan',
            'module_icon' => 'fa-circle-question',
            'user_name' => $user?->getFullname() ?: ($user?->getUsername() ?? 'Petugas'),
            'user_role' => $user?->getRole() ?? 'staff',
            'navigations' => $this->navBuilder->buildForUser($user),
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
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
