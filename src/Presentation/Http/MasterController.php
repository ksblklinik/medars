<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Master\Repository\MasterDataRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class MasterController
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

        $body = $this->renderer->render('master/index', [
            'judul' => 'Master Data Klinik & Tarif',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/master',
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
        try {
            $q = trim((string) ($query['q'] ?? ''));
            return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->list($entity, $q)]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSave(string $entity, array $payload): array
    {
        try {
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
            $this->repo->delete($entity, $payload);
            return $this->jsonResponse(['status' => 'success', 'message' => "Data {$entity} berhasil dihapus."]);
        } catch (\InvalidArgumentException|\DomainException $e) {
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
