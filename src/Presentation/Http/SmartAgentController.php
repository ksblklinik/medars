<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Rme\UseCase\RecommendClinicalPdtUseCase;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\SmartAgentRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class SmartAgentController
{
    public function __construct(
        private SmartAgentRepositoryInterface $repo,
        private RecommendClinicalPdtUseCase $recommendUseCase,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {
    }

    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('smartagent/index', [
            'judul' => 'SmartAgent AI Klinis & Knowledge Vault',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/smartagent',
            'csrf_token' => $csrfToken,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    public function apiKnowledgeList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listKnowledge(
                trim((string) ($query['q'] ?? '')),
                trim((string) ($query['category'] ?? ''))
            ),
        ]);
    }

    public function apiKnowledgeSave(?UserAccount $actor, array $post): array
    {
        $title = trim((string) ($post['title'] ?? ''));
        $content = trim((string) ($post['content'] ?? ''));
        if ($title === '' || $content === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Judul dan isi pedoman wajib diisi.']);
        }
        $post['created_by'] = $actor?->getUsername() ?? 'system';
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveKnowledge($post)]);
    }

    public function apiKnowledgeDelete(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0 || !$this->repo->deleteKnowledge($id)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Dokumen Knowledge Vault tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Dokumen dihapus.']);
    }

    public function apiProfiles(): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listProfiles()]);
    }

    public function apiProfileSave(array $post): array
    {
        if (trim((string) ($post['name'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Nama profil model AI wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveProfile($post)]);
    }

    public function apiRecommend(array $post): array
    {
        $q = trim((string) ($post['query'] ?? ''));
        if ($q === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Keluhan atau kata kunci klinis wajib diisi.']);
        }
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->recommendUseCase->recommend($q),
        ]);
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
