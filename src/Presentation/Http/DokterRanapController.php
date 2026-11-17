<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Ranap\Repository\DokterRanapRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class DokterRanapController
{
    public function __construct(
        private DokterRanapRepositoryInterface $repo,
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

        $body = $this->renderer->render('dokter_ranap/index', [
            'judul' => 'Dokter Rawat Inap (CPPT & Visite)',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/dokter-ranap',
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
    public function apiPatients(array $query = []): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listRanapPatients($q)]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListCppt(array $query = []): array
    {
        $nr = trim((string) ($query['no_rawat'] ?? ''));
        if ($nr === '') {
            return $this->jsonResponse(['status' => 'success', 'data' => []]);
        }
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listCppt($nr)]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveCppt(?UserAccount $actor, array $payload): array
    {
        try {
            $payload['nip'] = $actor?->getUsername() ?? (string) ($payload['nip'] ?? 'DR01');
            $this->repo->saveCppt($payload);
            return $this->jsonResponse(['status' => 'success', 'message' => 'CPPT Ranap berhasil disimpan.']);
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
